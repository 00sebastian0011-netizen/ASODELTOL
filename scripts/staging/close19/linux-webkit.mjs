/** Loopback TCP relay preserves Moodle's canonical URL inside the isolated browser container. */
import net from 'node:net';
const sockets=new Set();
const server=net.createServer(socket=>{
  const upstream=net.connect(80,'asodeltol-close19-webserver-1');sockets.add(socket);sockets.add(upstream);
  socket.pipe(upstream);upstream.pipe(socket);
  socket.on('error',()=>upstream.destroy());upstream.on('error',()=>socket.destroy());
  socket.on('close',()=>{sockets.delete(socket);upstream.destroy();});upstream.on('close',()=>sockets.delete(upstream));
});
await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(8054,'127.0.0.1',resolve);});
process.env.CLOSE19_BROWSER_ONLY='1';process.argv[2]='webkit';
try{await import('./browser.mjs');}finally{for(const socket of sockets)socket.destroy();server.close();}
