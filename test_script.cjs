const Pusher = require('pusher-js');
const pusher = new Pusher('dummy', { cluster: 'mt1' });
const channel = pusher.subscribe('dummy-channel');
console.log(Object.keys(channel.__proto__));
