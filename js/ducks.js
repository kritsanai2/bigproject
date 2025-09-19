const cursor = document.getElementById('cursor');
let mouseX = 0;
let mouseY = 0;

document.addEventListener('mousemove', (e) => {
  mouseX = e.clientX;
  mouseY = e.clientY;
  cursor.style.left = mouseX + 'px';
  cursor.style.top = mouseY + 'px';
});

// สร้างฝูงเป็ด
const NUM_DUCKS = 5;
const ducks = [];
for(let i=0; i<NUM_DUCKS; i++){
  const d = document.createElement('div');
  d.className = 'duck';
  d.textContent = '🦆'; // ใช้ Emoji
  d.style.left = (Math.random()*200) + 'px';
  d.style.top = (Math.random()*200) + 'px';
  document.body.appendChild(d);

  ducks.push({
    el: d,
    x: parseFloat(d.style.left),
    y: parseFloat(d.style.top),
    zigX: Math.random()*20-10,
    zigY: Math.random()*10-5,
    zigTimer: Math.floor(Math.random()*20)
  });
}

// ระยะห่างขั้นต่ำ
const minDistance = 100;

function followMouse(){
  ducks.forEach(duck=>{
    duck.zigTimer++;
    if(duck.zigTimer>20){
      duck.zigX = Math.random()*20-10;
      duck.zigY = Math.random()*10-5;
      duck.zigTimer = 0;
    }

    const targetX = mouseX + duck.zigX;
    const targetY = mouseY + duck.zigY;
    const dx = targetX - duck.x;
    const dy = targetY - duck.y;
    const dist = Math.sqrt(dx*dx + dy*dy);

    if(dist > minDistance){
      duck.x += dx/dist*(dist-minDistance)*0.01; // ช้ามาก
      duck.y += dy/dist*(dist-minDistance)*0.01;
    }

    duck.el.style.left = duck.x + 'px';
    duck.el.style.top = duck.y + 'px';
  });

  requestAnimationFrame(followMouse);
}

followMouse();
