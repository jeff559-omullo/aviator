const io = require('socket.io-client');
const socket = io('http://127.0.0.1:3000');
console.log('connecting to socket server...');
socket.on('connect', () => {
  console.log('connected', socket.id);
});
socket.on('forceCrash', (payload) => {
  console.log('forceCrash received', payload);
});
setInterval(() => {}, 1e6);
