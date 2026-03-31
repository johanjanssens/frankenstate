// Package resp provides a Redis wire protocol (RESP) server that wraps the
// shared state store. Any Redis client can connect and read/write the same
// data that Go extensions and PHP see.
//
//	redis-cli -p 6380 SET theme dark
//	redis-cli -p 6380 GET theme
//	redis-cli -p 6380 KEYS '*'
package resp

import (
	"encoding/json"
	"fmt"
	"log/slog"
	"strconv"
	"strings"

	"github.com/johanjanssens/frankenstate/state"
	"github.com/tidwall/match"
	"github.com/tidwall/redcon"
)

// Server is a Redis-compatible server backed by the shared state store.
type Server struct {
	addr   string
	server *redcon.Server
	logger *slog.Logger
}

// New creates a RESP server on the given address.
func New(addr string, logger *slog.Logger) *Server {
	s := &Server{
		addr:   addr,
		logger: logger,
	}

	s.server = redcon.NewServer(addr, s.handle, s.accept, s.closed)
	return s
}

// ListenAndServe starts the RESP server. Blocks until closed.
func (s *Server) ListenAndServe() error {
	s.logger.Info("Starting RESP server", "addr", s.addr)
	return s.server.ListenAndServe()
}

// Close shuts down the RESP server.
func (s *Server) Close() error {
	return s.server.Close()
}

func (s *Server) accept(conn redcon.Conn) bool {
	return true
}

func (s *Server) closed(conn redcon.Conn, err error) {
}

func (s *Server) handle(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) == 0 {
		conn.WriteError("ERR empty command")
		return
	}

	switch strings.ToUpper(string(cmd.Args[0])) {
	case "PING":
		s.cmdPing(conn, cmd)
	case "GET":
		s.cmdGet(conn, cmd)
	case "SET":
		s.cmdSet(conn, cmd)
	case "DEL":
		s.cmdDel(conn, cmd)
	case "EXISTS":
		s.cmdExists(conn, cmd)
	case "KEYS":
		s.cmdKeys(conn, cmd)
	case "MGET":
		s.cmdMget(conn, cmd)
	case "MSET":
		s.cmdMset(conn, cmd)
	case "INCR":
		s.cmdIncr(conn, cmd, 1)
	case "DECR":
		s.cmdIncr(conn, cmd, -1)
	case "INCRBY":
		s.cmdIncrBy(conn, cmd)
	case "DECRBY":
		s.cmdDecrBy(conn, cmd)
	case "DBSIZE":
		conn.WriteInt(state.Len())
	case "FLUSHDB", "FLUSHALL":
		state.Replace(make(map[string]any))
		conn.WriteString("OK")
	case "INFO":
		s.cmdInfo(conn)
	case "TYPE":
		s.cmdType(conn, cmd)
	case "SELECT":
		conn.WriteString("OK") // single db, no-op
	case "COMMAND":
		conn.WriteArray(0) // enough to satisfy redis-cli handshake
	case "QUIT":
		conn.WriteString("OK")
		conn.Close()
	default:
		conn.WriteError(fmt.Sprintf("ERR unknown command '%s'", cmd.Args[0]))
	}
}

// PING [message]
func (s *Server) cmdPing(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) > 1 {
		conn.WriteBulk(cmd.Args[1])
	} else {
		conn.WriteString("PONG")
	}
}

// GET key
func (s *Server) cmdGet(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) != 2 {
		conn.WriteError("ERR wrong number of arguments for 'GET' command")
		return
	}

	key := string(cmd.Args[1])
	val, ok := state.Get(key)
	if !ok {
		conn.WriteNull()
		return
	}

	conn.WriteBulkString(toRedisString(val))
}

// SET key value
func (s *Server) cmdSet(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) < 3 {
		conn.WriteError("ERR wrong number of arguments for 'SET' command")
		return
	}

	key := string(cmd.Args[1])
	val := string(cmd.Args[2])

	// Try to preserve type: integer, float, bool, or string
	if i, err := strconv.ParseInt(val, 10, 64); err == nil {
		state.Set(key, i)
	} else if f, err := strconv.ParseFloat(val, 64); err == nil {
		state.Set(key, f)
	} else {
		state.Set(key, val)
	}

	conn.WriteString("OK")
}

// DEL key [key ...]
func (s *Server) cmdDel(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) < 2 {
		conn.WriteError("ERR wrong number of arguments for 'DEL' command")
		return
	}

	deleted := 0
	for _, arg := range cmd.Args[1:] {
		key := string(arg)
		if state.Has(key) {
			state.Delete(key)
			deleted++
		}
	}

	conn.WriteInt(deleted)
}

// EXISTS key [key ...]
func (s *Server) cmdExists(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) < 2 {
		conn.WriteError("ERR wrong number of arguments for 'EXISTS' command")
		return
	}

	count := 0
	for _, arg := range cmd.Args[1:] {
		if state.Has(string(arg)) {
			count++
		}
	}

	conn.WriteInt(count)
}

// KEYS pattern
func (s *Server) cmdKeys(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) != 2 {
		conn.WriteError("ERR wrong number of arguments for 'KEYS' command")
		return
	}

	pattern := string(cmd.Args[1])
	keys := state.Keys()

	var matched []string
	for _, k := range keys {
		if match.Match(k, pattern) {
			matched = append(matched, k)
		}
	}

	conn.WriteArray(len(matched))
	for _, k := range matched {
		conn.WriteBulkString(k)
	}
}

// MGET key [key ...]
func (s *Server) cmdMget(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) < 2 {
		conn.WriteError("ERR wrong number of arguments for 'MGET' command")
		return
	}

	conn.WriteArray(len(cmd.Args) - 1)
	for _, arg := range cmd.Args[1:] {
		val, ok := state.Get(string(arg))
		if !ok {
			conn.WriteNull()
		} else {
			conn.WriteBulkString(toRedisString(val))
		}
	}
}

// MSET key value [key value ...]
func (s *Server) cmdMset(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) < 3 || len(cmd.Args)%2 == 0 {
		conn.WriteError("ERR wrong number of arguments for 'MSET' command")
		return
	}

	m := make(map[string]any, (len(cmd.Args)-1)/2)
	for i := 1; i < len(cmd.Args); i += 2 {
		key := string(cmd.Args[i])
		val := string(cmd.Args[i+1])

		if integer, err := strconv.ParseInt(val, 10, 64); err == nil {
			m[key] = integer
		} else if f, err := strconv.ParseFloat(val, 64); err == nil {
			m[key] = f
		} else {
			m[key] = val
		}
	}

	state.Merge(m)
	conn.WriteString("OK")
}

// INCR/DECR key (delta = +1 or -1)
func (s *Server) cmdIncr(conn redcon.Conn, cmd redcon.Command, delta int64) {
	if len(cmd.Args) != 2 {
		conn.WriteError(fmt.Sprintf("ERR wrong number of arguments for '%s' command", cmd.Args[0]))
		return
	}

	key := string(cmd.Args[1])
	result, err := atomicAdd(key, delta)
	if err != nil {
		conn.WriteError(err.Error())
		return
	}

	conn.WriteInt64(result)
}

// INCRBY key increment
func (s *Server) cmdIncrBy(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) != 3 {
		conn.WriteError("ERR wrong number of arguments for 'INCRBY' command")
		return
	}

	delta, err := strconv.ParseInt(string(cmd.Args[2]), 10, 64)
	if err != nil {
		conn.WriteError("ERR value is not an integer or out of range")
		return
	}

	key := string(cmd.Args[1])
	result, err := atomicAdd(key, delta)
	if err != nil {
		conn.WriteError(err.Error())
		return
	}

	conn.WriteInt64(result)
}

// DECRBY key decrement
func (s *Server) cmdDecrBy(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) != 3 {
		conn.WriteError("ERR wrong number of arguments for 'DECRBY' command")
		return
	}

	delta, err := strconv.ParseInt(string(cmd.Args[2]), 10, 64)
	if err != nil {
		conn.WriteError("ERR value is not an integer or out of range")
		return
	}

	key := string(cmd.Args[1])
	result, err := atomicAdd(key, -delta)
	if err != nil {
		conn.WriteError(err.Error())
		return
	}

	conn.WriteInt64(result)
}

// TYPE key
func (s *Server) cmdType(conn redcon.Conn, cmd redcon.Command) {
	if len(cmd.Args) != 2 {
		conn.WriteError("ERR wrong number of arguments for 'TYPE' command")
		return
	}

	val, ok := state.Get(string(cmd.Args[1]))
	if !ok {
		conn.WriteString("none")
		return
	}

	switch val.(type) {
	case string:
		conn.WriteString("string")
	case int, int64, float64, json.Number:
		conn.WriteString("string") // Redis stores numbers as strings
	case bool:
		conn.WriteString("string")
	case map[string]any:
		conn.WriteString("hash")
	case []any:
		conn.WriteString("list")
	default:
		conn.WriteString("string")
	}
}

// INFO
func (s *Server) cmdInfo(conn redcon.Conn) {
	snap := state.Snapshot()
	info := fmt.Sprintf(
		"# Server\r\nfrankenstate_version:0.1.0\r\nredis_version:7.0.0\r\ntcp_port:%s\r\n\r\n"+
			"# Keyspace\r\ndb0:keys=%d\r\n\r\n"+
			"# State\r\nversion:%d\r\n",
		s.addr, len(snap), state.Version(),
	)

	conn.WriteBulkString(info)
}

// atomicAdd reads the current value, adds delta, stores back.
// Returns the new value or error if current value isn't numeric.
func atomicAdd(key string, delta int64) (int64, error) {
	val, ok := state.Get(key)
	if !ok {
		// Key doesn't exist — start from 0
		state.Set(key, delta)
		return delta, nil
	}

	var current int64
	switch v := val.(type) {
	case int64:
		current = v
	case int:
		current = int64(v)
	case float64:
		if v != float64(int64(v)) {
			return 0, fmt.Errorf("ERR value is not an integer or out of range")
		}
		current = int64(v)
	case json.Number:
		i, err := v.Int64()
		if err != nil {
			return 0, fmt.Errorf("ERR value is not an integer or out of range")
		}
		current = i
	case string:
		i, err := strconv.ParseInt(v, 10, 64)
		if err != nil {
			return 0, fmt.Errorf("ERR value is not an integer or out of range")
		}
		current = i
	default:
		return 0, fmt.Errorf("ERR value is not an integer or out of range")
	}

	result := current + delta
	state.Set(key, result)
	return result, nil
}

// toRedisString converts any Go value to a Redis bulk string.
func toRedisString(val any) string {
	switch v := val.(type) {
	case string:
		return v
	case int64:
		return strconv.FormatInt(v, 10)
	case int:
		return strconv.Itoa(v)
	case float64:
		return strconv.FormatFloat(v, 'f', -1, 64)
	case bool:
		if v {
			return "1"
		}
		return "0"
	case json.Number:
		return v.String()
	case nil:
		return ""
	default:
		b, err := json.Marshal(v)
		if err != nil {
			return fmt.Sprintf("%v", v)
		}
		return string(b)
	}
}
