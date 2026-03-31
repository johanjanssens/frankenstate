package main

import (
	"context"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"strconv"
	"strings"
	"sync/atomic"
	"syscall"
	"time"

	_ "github.com/johanjanssens/frankenstate/phpext" // registers PHP extension via init()

	"github.com/johanjanssens/frankenstate/resp"
	"github.com/johanjanssens/frankenstate/state"

	"github.com/dunglas/frankenphp"
	"github.com/joho/godotenv"
	"github.com/lmittmann/tint"
)

func main() {
	_ = godotenv.Load()

	logger := slog.New(tint.NewHandler(os.Stdout, &tint.Options{
		Level:      slog.LevelDebug,
		TimeFormat: time.Kitchen,
	}))
	slog.SetDefault(logger)

	ctx, cancel := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer cancel()

	// Resolve document root
	docRootDir := "examples"
	if dir := os.Getenv("FRANKENSTATE_DOC_ROOT"); dir != "" {
		docRootDir = dir
	}
	docRoot, err := filepath.Abs(docRootDir)
	if err != nil {
		logger.Error("Failed to resolve document root", "error", err)
		os.Exit(1)
	}

	numThreads := 2
	if n, err := strconv.Atoi(os.Getenv("FRANKENSTATE_THREADS")); err == nil && n > 0 {
		numThreads = n
	}

	// Seed state from Go — demonstrates Go → PHP communication.
	startTime := time.Now()
	state.Set("server.started_at", startTime.Format(time.RFC3339))
	state.Set("server.pid", os.Getpid())
	state.Set("server.threads", numThreads)
	state.Set("server.requests", int64(0))
	logger.Info("Seeded initial state from Go", "version", state.Version())

	// Request counter (atomic, pushed to state on each request)
	var requestCount atomic.Int64

	// Init FrankenPHP
	initOptions := []frankenphp.Option{
		frankenphp.WithNumThreads(numThreads),
		frankenphp.WithLogger(logger),
		frankenphp.WithPhpIni(map[string]string{
			"include_path": docRoot,
		}),
	}

	if err := frankenphp.Init(initOptions...); err != nil {
		logger.Error("Failed to initialize FrankenPHP", "error", err)
		os.Exit(1)
	}
	defer frankenphp.Shutdown()

	// HTTP server
	addr := ":8083"
	if port := os.Getenv("FRANKENSTATE_PORT"); port != "" {
		addr = ":" + port
	}

	fileServer := http.FileServer(http.Dir(docRoot))

	mux := http.NewServeMux()
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/" || strings.HasSuffix(r.URL.Path, "/") {
			r.URL.Path = r.URL.Path + "index.php"
		}

		if !strings.HasSuffix(r.URL.Path, ".php") {
			fileServer.ServeHTTP(w, r)
			return
		}

		// Push live metrics into state on every PHP request
		count := requestCount.Add(1)
		state.Set("server.requests", count)
		state.Set("server.uptime_seconds", int(time.Since(startTime).Seconds()))
		state.Set("server.last_request", time.Now().Format(time.RFC3339))

		req, err := frankenphp.NewRequestWithContext(r,
			frankenphp.WithRequestResolvedDocumentRoot(docRoot),
			frankenphp.WithRequestLogger(logger),
		)
		if err != nil {
			logger.Error("Failed to create FrankenPHP request", "error", err)
			http.Error(w, "Internal server error", http.StatusInternalServerError)
			return
		}

		if err := frankenphp.ServeHTTP(w, req); err != nil {
			logger.Error("Failed to serve PHP", "error", err)
		}
	})

	server := &http.Server{
		Addr:         addr,
		Handler:      mux,
		ReadTimeout:  30 * time.Second,
		WriteTimeout: 120 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	// RESP server (Redis wire protocol)
	redisAddr := ":6380"
	if port := os.Getenv("FRANKENSTATE_REDIS_PORT"); port != "" {
		redisAddr = ":" + port
	}

	respServer := resp.New(redisAddr, logger)
	go func() {
		if err := respServer.ListenAndServe(); err != nil {
			logger.Error("RESP server error", "error", err)
		}
	}()

	go func() {
		logger.Info("Starting FrankenState server", "http", addr, "redis", redisAddr, "docroot", docRoot)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Error("Server error", "error", err)
			cancel()
		}
	}()

	<-ctx.Done()
	logger.Info("Shutting down...")

	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer shutdownCancel()
	_ = respServer.Close()
	if err := server.Shutdown(shutdownCtx); err != nil {
		logger.Error("Failed to shutdown server", "error", err)
	}
}
