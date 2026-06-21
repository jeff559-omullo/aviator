const express = require('express');
const http = require('http');
const cors = require('cors');
const app = express();
app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use(cors());
const server = http.createServer(app);
const { Server } = require('socket.io');
const io = new Server(server, { cors: { origin: '*' } });

io.on('connection', (socket) => {
  console.log('socket connected:', socket.id);
});

app.post('/emitCrash', (req, res) => {
  const last_time = req.body.last_time || req.query.last_time || req.headers['x-last-time'];
  const delay = req.body.delay || req.query.delay || req.headers['x-delay'];
  const payload = { last_time: parseFloat(last_time) };
  if (delay && !isNaN(parseFloat(delay)) && parseFloat(delay) > 0) {
    // schedule emit after delay seconds
    setTimeout(() => {
      io.emit('forceCrash', payload);
    }, parseFloat(delay) * 1000);
  } else {
    io.emit('forceCrash', payload);
  }
  res.json({ ok: true });
});

const port = process.env.PORT || 3000;
server.listen(port, () => console.log('Socket server listening on', port));
