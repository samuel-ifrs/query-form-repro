// Minimal, zero-dependency repro for whatwg/html#12594
// Demonstrates that browsers currently fall back to GET for <form method="query">,
// silently dropping the request body — while fetch() does send QUERY on the wire.
//
// Run:  node server.js   then open http://localhost:8790

const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

const PORT = 8790;
const INDEX = path.join(__dirname, 'index.html');

function parseParams(str) {
  const out = {};
  for (const [k, v] of new URLSearchParams(str)) out[k] = v;
  return out;
}

function resultPage({ intended, actual, where, data }) {
  const fellBack = intended === 'QUERY' && actual !== 'QUERY';
  const verdict = fellBack
    ? `<p class="warn">⚠️ The browser did NOT send QUERY — it fell back to <b>${actual}</b>, and the body was dropped. This confirms forms don't support QUERY yet.</p>`
    : intended === 'QUERY'
    ? `<p class="ok">✅ The browser sent QUERY on the wire.</p>`
    : `<p class="ok">✅ ${actual} as expected.</p>`;
  return `<!doctype html><meta charset="utf-8"><title>Result</title>
<style>body{font:15px/1.6 ui-monospace,Menlo,Consolas,monospace;background:#0d1117;color:#e6edf3;
display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}
.card{width:min(620px,100%);background:#161b22;border:1px solid #30363d;border-radius:12px;padding:28px}
.k{color:#79c0ff}.ok{color:#3fb950}.warn{color:#d29922}a{color:#79c0ff}
pre{background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:14px;overflow-x:auto}</style>
<div class="card">
<h1>Submission result</h1>
<p><span class="k">submitted via:</span> ${where}</p>
<p><span class="k">method attribute / intent:</span> ${intended}</p>
<p><span class="k">method that reached the server:</span> <b>${actual}</b></p>
${verdict}
<p class="k">data received by server:</p>
<pre>${JSON.stringify(data, null, 2)}</pre>
<a href="/">← back</a>
</div>`;
}

http
  .createServer((req, res) => {
    const u = new URL(req.url, `http://localhost:${PORT}`);

    if (u.pathname === '/') {
      res.setHeader('Content-Type', 'text/html; charset=utf-8');
      res.end(fs.readFileSync(INDEX));
      return;
    }

    if (u.pathname === '/submit') {
      const done = (data) => {
        const intended = data.__intended || 'unknown';
        const where = data.__via || 'form';
        delete data.__intended;
        delete data.__via;
        console.log(
          `>> /submit  intended=${intended.padEnd(6)} actual=${req.method.padEnd(6)} via=${where}  data=${JSON.stringify(data)}`
        );
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        res.end(resultPage({ intended, actual: req.method, where, data }));
      };
      if (req.method === 'GET' || req.method === 'HEAD') {
        done(parseParams(u.search.slice(1)));
      } else {
        let body = '';
        req.on('data', (c) => (body += c));
        req.on('end', () => done(parseParams(body)));
      }
      return;
    }

    res.writeHead(404).end('not found');
  })
  .listen(PORT, () => {
    console.log(`Repro running at  http://localhost:${PORT}`);
    console.log('Each submission is logged below (watch the "actual" column):\n');
  });
