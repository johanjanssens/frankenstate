<?php header('Content-Type: text/css'); ?>
/* Reset & base */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f5f5f5;
}

/* Nav */
.nav { padding: 12px 24px; background: white; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
.nav a { color: #1976d2; text-decoration: none; font-weight: 500; }
.nav a:hover { text-decoration: underline; }
.nav-links { display: flex; gap: 16px; }
.nav-links a { color: #1976d2; text-decoration: none; font-weight: 500; }
.nav-links a:hover { text-decoration: underline; }

/* Theme toggle */
.theme-toggle { background: none; border: 1px solid #ddd; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-size: 1.1rem; color: #666; line-height: 1; }
.theme-toggle:hover { background: #f0f0f0; }

/* Intro */
.intro {
    max-width: 900px;
    margin: 0 auto 24px;
    padding: 24px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    position: relative;
}
.intro h1 { font-size: 1.6rem; color: #2c3e50; margin-bottom: 8px; }
.intro p { color: #666; margin-bottom: 8px; }
.intro code { background: #f6f8fa; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
.intro a { color: #1976d2; }

/* Explainer (collapsible) */
.explainer,
.intro-explain {
    background: #f8f9ff;
    border: 1px solid #e0e4f0;
    border-radius: 12px;
    font-size: 0.88rem;
    line-height: 1.65;
    color: #555;
}
.explainer { max-width: 900px; margin: 0 auto 32px; }
.intro-explain { text-align: left; margin-top: 16px; }
.explainer summary,
.intro-explain summary {
    padding: 14px 20px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 8px;
    user-select: none;
}
.explainer summary { color: #4527a0; }
.intro-explain summary { color: #555; }
.explainer summary::before,
.intro-explain summary::before {
    content: '\25B6';
    font-size: 0.65em;
    transition: transform 0.25s ease;
    display: inline-block;
}
.explainer[open] > summary::before,
.intro-explain[open] > summary::before {
    transform: rotate(90deg);
}
.explainer summary::-webkit-details-marker,
.intro-explain summary::-webkit-details-marker { display: none; }
.explainer-body {
    padding: 0 20px 16px;
    overflow: hidden;
}
.explainer-body p { margin-bottom: 8px; color: inherit; }
.explainer-body p:last-child { margin-bottom: 0; }
.explainer-body strong { color: #333; }
.explainer-body a { color: #4527a0; }
.explainer-body code { background: #f0f0f5; padding: 1px 5px; border-radius: 3px; font-size: 0.9em; }
.explainer-sub { font-size: 0.85rem !important; color: #777 !important; }
.explainer-stack {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin: 14px 0;
    font-size: 0.82rem;
    font-weight: 600;
}
.explainer-stack span {
    background: white;
    border: 1px solid #d0d4e0;
    padding: 4px 12px;
    border-radius: 6px;
    color: #333;
}
.explainer-stack .arrow { background: none; border: none; color: #999; padding: 0 2px; }

/* Badges */
.intro-badges { position: absolute; top: 16px; right: 16px; display: flex; gap: 8px; align-items: center; }
.badge { padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.badge-state { background: #e8f5e9; color: #2e7d32; }
.badge-go { background: #e0f2f1; color: #00695c; }
.badge-php { background: #ede7f6; color: #4527a0; }
.badge-version { background: #f3f4f6; color: #6b7280; font-family: monospace; }

/* Container */
.container { max-width: 900px; margin: 0 auto; }
.panel {
    overflow: auto;
    padding: 20px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}
.panel h2 { font-size: 1.2rem; color: #2c3e50; margin-bottom: 16px; }

/* Timing */
.timing { font-size: 13px; color: #3a5d7a; font-weight: 600; background: #e8f4fd; padding: 4px 12px; border-radius: 20px; }

/* Tables */
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #eee; }
th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; font-weight: 600; }
td { font-size: 0.9rem; }
td.key { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.85rem; color: #1976d2; font-weight: 500; }
td.val { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.85rem; word-break: break-all; }
td.source { font-size: 0.75rem; }
.source-go { background: #e0f2f1; color: #00695c; padding: 2px 8px; border-radius: 3px; font-weight: 600; }
.source-php { background: #ede7f6; color: #4527a0; padding: 2px 8px; border-radius: 3px; font-weight: 600; }

/* Metric cards */
.metrics { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px; }
.metric {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}
.metric-value {
    font-size: 1.8rem;
    font-weight: 700;
    font-family: 'SF Mono', 'Fira Code', monospace;
    color: #1a1a2e;
    line-height: 1.2;
}
.metric-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; font-weight: 600; margin-top: 4px; }

/* Forms */
.form-row { display: flex; gap: 12px; margin-bottom: 16px; }
.form-row input[type="text"] {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    font-family: 'SF Mono', 'Fira Code', monospace;
}
.form-row input[type="text"]:focus { outline: none; border-color: #1976d2; box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1); }
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    text-decoration: none;
    display: inline-block;
    line-height: 1.4;
}
.btn-primary { background: #1976d2; color: white; }
.btn-primary:hover { background: #1565c0; }
.btn-danger { background: #ef5350; color: white; font-size: 0.8rem; padding: 5px 12px; }
.btn-danger:hover { background: #e53935; }
.btn-sm { padding: 5px 12px; font-size: 0.8rem; }

/* Empty state */
.empty { text-align: center; color: #999; padding: 32px; font-style: italic; }

/* Pre */
pre {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 4px;
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    border: 1px solid #eee;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-x: auto;
}

/* Footer */
footer { max-width: 900px; margin: 40px auto 20px; text-align: center; color: #999; font-size: 0.85rem; }
footer a { color: #999; text-decoration: underline; }

/* Dark theme */
html.dark body { background-color: #1a1a2e; color: #e0e0e0; }
html.dark .nav { background: #16213e; box-shadow: 0 1px 4px rgba(0,0,0,0.3); }
html.dark .nav a, html.dark .nav-links a { color: #64b5f6; }
html.dark .intro { background: #16213e; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
html.dark .intro h1 { color: #e0e0e0; }
html.dark .intro p { color: #aaa; }
html.dark .intro code { background: #1e2a45; color: #cdd6f4; }
html.dark .intro a { color: #64b5f6; }
html.dark .explainer,
html.dark .intro-explain { background: #141a2e; border-color: #2a2f45; color: #aaa; }
html.dark .explainer summary { color: #b39ddb; }
html.dark .intro-explain summary { color: #aaa; }
html.dark .explainer-body strong { color: #ccc; }
html.dark .explainer-body a { color: #b39ddb; }
html.dark .explainer-body code { background: #1e2a45; color: #cdd6f4; }
html.dark .explainer-sub { color: #888 !important; }
html.dark .explainer-stack span { background: #1a2040; border-color: #2a3050; color: #ccc; }
html.dark .explainer-stack .arrow { color: #666; }
html.dark .badge-state { background: #1a332e; color: #4db6ac; }
html.dark .badge-go { background: #1a332e; color: #4db6ac; }
html.dark .badge-php { background: #1f1a33; color: #b39ddb; }
html.dark .badge-version { background: #1e2a45; color: #8b95a5; }
html.dark .panel { background: #16213e; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
html.dark .panel h2 { color: #e0e0e0; }
html.dark .metric { background: #1e2a45; }
html.dark .metric-value { color: #e0e0e0; }
html.dark table th { color: #888; }
html.dark table td { border-bottom-color: #2a3a5c; }
html.dark td.key { color: #64b5f6; }
html.dark .source-go { background: #1a332e; color: #4db6ac; }
html.dark .source-php { background: #1f1a33; color: #b39ddb; }
html.dark .form-row input[type="text"] { background: #1e2a45; border-color: #2a3a5c; color: #e0e0e0; }
html.dark .form-row input[type="text"]:focus { border-color: #64b5f6; box-shadow: 0 0 0 3px rgba(100, 181, 246, 0.1); }
html.dark pre { background: #0d1117; border-color: #21262d; color: #cdd6f4; }
html.dark .timing { background: #1e2a45; color: #64b5f6; }
html.dark .theme-toggle { border-color: #444; color: #ccc; }
html.dark .theme-toggle:hover { background: #2a3a5c; }
html.dark footer { color: #666; }
html.dark footer a { color: #666; }

/* Responsive */
@media (max-width: 768px) {
    .metrics { grid-template-columns: repeat(2, 1fr); }
    .form-row { flex-direction: column; }
}
