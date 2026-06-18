<!DOCTYPE html>
<html lang="nl">
<head>
<link rel="icon" href="../assets/favicon.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>tetris</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: #f0ece3;
  font-family: 'IBM Plex Mono', monospace;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 24px 16px;
  gap: 0;
}

h1 {
  font-size: 13px;
  font-weight: 400;
  letter-spacing: 0.25em;
  color: #999;
  margin-bottom: 20px;
  text-transform: lowercase;
}

.game-area {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

/* the main board */
#game-canvas {
  display: block;
  border: 2px solid #222;
  image-rendering: pixelated;
}

/* side stuff */
.side {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding-top: 2px;
}

.side-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.side-label {
  font-size: 9px;
  letter-spacing: 0.2em;
  color: #aaa;
  text-transform: lowercase;
}

.side-value {
  font-size: 20px;
  font-weight: 700;
  color: #222;
  line-height: 1;
}

.side-value.mono-sm {
  font-size: 14px;
}

#next-canvas {
  display: block;
  border: 1px solid #ddd;
  background: #f8f5f0;
}

/* controls hint */
.hint {
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.hint-row {
  font-size: 9px;
  color: #bbb;
  letter-spacing: 0.05em;
}

.hint-row b {
  color: #888;
  font-weight: 700;
}

/* overlay */
.overlay-wrap {
  position: relative;
}

.overlay {
  position: absolute;
  inset: 0;
  background: #f0ece3;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 20px;
  text-align: center;
  border: 2px solid #222;
  transition: opacity 0.2s;
}

.overlay.off {
  opacity: 0;
  pointer-events: none;
}

.ov-title {
  font-size: 26px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #222;
  line-height: 1;
}

.ov-sub {
  font-size: 10px;
  color: #aaa;
  letter-spacing: 0.1em;
  line-height: 1.8;
}

.ov-score-label {
  font-size: 9px;
  color: #aaa;
  letter-spacing: 0.2em;
  margin-bottom: -8px;
}

.ov-score {
  font-size: 32px;
  font-weight: 700;
  color: #222;
}

.btn {
  font-family: 'IBM Plex Mono', monospace;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: lowercase;
  padding: 10px 22px;
  background: #222;
  color: #f0ece3;
  border: none;
  cursor: pointer;
}

.btn:hover { background: #444; }
.btn:active { transform: scale(0.98); }

/* mobile buttons */
.mobile-row {
  display: none;
  gap: 8px;
  margin-top: 14px;
  justify-content: center;
}

.m-btn {
  width: 54px;
  height: 54px;
  background: #fff;
  border: 1px solid #ccc;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: monospace;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}

.m-btn:active { background: #e8e4db; }
.m-btn.wide { width: 116px; font-size: 10px; letter-spacing: 0.1em; }

@media (max-width: 520px) {
  .side { display: none; }
  .mobile-row { display: flex; flex-wrap: wrap; width: 210px; }
}
</style>
</head>
<body>

<h1>tetris</h1>

<div class="game-area">
  <div class="overlay-wrap">
    <canvas id="game-canvas" width="250" height="500"></canvas>

    <div class="overlay" id="ov-start">
      <div class="ov-title">tetris</div>
      <div class="ov-sub">
        ← → bewegen<br>
        ↑ roteren<br>
        ↓ soft drop<br>
        spatie hard drop<br>
        p pauze
      </div>
      <button class="btn" id="btn-start">start</button>
    </div>

    <div class="overlay off" id="ov-over">
      <div class="ov-title">game<br>over</div>
      <div class="ov-score-label">score</div>
      <div class="ov-score" id="final-score">0</div>
      <button class="btn" id="btn-restart">opnieuw</button>
    </div>

    <div class="overlay off" id="ov-pause">
      <div class="ov-title">pauze</div>
      <div class="ov-sub">p om verder<br>te gaan</div>
    </div>
  </div>

  <div class="side">
    <div class="side-block">
      <div class="side-label">volgend</div>
      <canvas id="next-canvas" width="90" height="72"></canvas>
    </div>
    <div class="side-block">
      <div class="side-label">score</div>
      <div class="side-value" id="disp-score">0</div>
    </div>
    <div class="side-block">
      <div class="side-label">level</div>
      <div class="side-value" id="disp-level">1</div>
    </div>
    <div class="side-block">
      <div class="side-label">lijnen</div>
      <div class="side-value" id="disp-lines">0</div>
    </div>
    <div class="side-block">
      <div class="side-label">record</div>
      <div class="side-value mono-sm" id="disp-hs">0</div>
    </div>
    <div class="hint">
      <div class="hint-row"><b>←→</b> bewegen</div>
      <div class="hint-row"><b>↑</b> roteren</div>
      <div class="hint-row"><b>↓</b> zakt</div>
      <div class="hint-row"><b>spc</b> drop</div>
      <div class="hint-row"><b>p</b> pauze</div>
    </div>
  </div>
</div>

<div class="mobile-row">
  <div style="display:flex;gap:8px;justify-content:center;width:100%">
    <button class="m-btn" id="ml">◀</button>
    <button class="m-btn" id="md">▼</button>
    <button class="m-btn" id="mr">▶</button>
  </div>
  <div style="display:flex;gap:8px;justify-content:center;width:100%">
    <button class="m-btn" id="mrot">↻</button>
    <button class="m-btn wide" id="mdrop">drop</button>
  </div>
</div>

<script>
const COLS = 10, ROWS = 20, B = 25;

const cv  = document.getElementById('game-canvas');
const cx  = cv.getContext('2d');
const nc  = document.getElementById('next-canvas');
const nx  = nc.getContext('2d');

// colours: deliberately a bit muted / imperfect
const COLORS = {
  I:'#5ba4cf', O:'#f5c842', T:'#9b7fe8',
  S:'#5cb85c', Z:'#e05c5c', J:'#4a90d9', L:'#e8843a'
};

const SHAPES = {
  I:[[[0,0],[0,1],[0,2],[0,3]],[[0,0],[1,0],[2,0],[3,0]],[[0,0],[0,1],[0,2],[0,3]],[[0,0],[1,0],[2,0],[3,0]]],
  O:[[[0,0],[0,1],[1,0],[1,1]],[[0,0],[0,1],[1,0],[1,1]],[[0,0],[0,1],[1,0],[1,1]],[[0,0],[0,1],[1,0],[1,1]]],
  T:[[[0,1],[1,0],[1,1],[1,2]],[[0,0],[1,0],[2,0],[1,1]],[[1,0],[1,1],[1,2],[2,1]],[[0,1],[1,0],[1,1],[2,1]]],
  S:[[[0,1],[0,2],[1,0],[1,1]],[[0,0],[1,0],[1,1],[2,1]],[[0,1],[0,2],[1,0],[1,1]],[[0,0],[1,0],[1,1],[2,1]]],
  Z:[[[0,0],[0,1],[1,1],[1,2]],[[0,1],[1,0],[1,1],[2,0]],[[0,0],[0,1],[1,1],[1,2]],[[0,1],[1,0],[1,1],[2,0]]],
  J:[[[0,0],[1,0],[1,1],[1,2]],[[0,0],[0,1],[1,0],[2,0]],[[1,0],[1,1],[1,2],[2,2]],[[0,1],[1,1],[2,0],[2,1]]],
  L:[[[0,2],[1,0],[1,1],[1,2]],[[0,0],[1,0],[2,0],[2,1]],[[1,0],[1,1],[1,2],[2,0]],[[0,0],[0,1],[1,1],[2,1]]],
};

const KEYS = Object.keys(SHAPES);

let board, cur, nxt, score, level, lines, running, paused, raf, accum, last;

function mkBoard() { return Array.from({length:ROWS},()=>Array(COLS).fill(0)); }

function rnd() {
  const k = KEYS[Math.floor(Math.random()*KEYS.length)];
  return {k, rot:0, r:0, c:Math.floor(COLS/2)-1};
}

function cells(p,r,c,rot){
  return SHAPES[p.k][rot].map(([dr,dc])=>[r+dr,c+dc]);
}

function ok(p,r,c,rot){
  return cells(p,r,c,rot).every(([rr,cc])=>rr>=0&&rr<ROWS&&cc>=0&&cc<COLS&&!board[rr][cc]);
}

function init(){
  board=mkBoard(); score=0; level=1; lines=0; paused=false; accum=0;
  nxt=rnd(); spawn(); ui();
}

function spawn(){
  cur=nxt; cur.r=0; cur.c=Math.floor(COLS/2)-1;
  nxt=rnd(); drawNext();
  if(!ok(cur,cur.r,cur.c,cur.rot)) over();
}

function left(){ if(ok(cur,cur.r,cur.c-1,cur.rot)) cur.c--; }
function right(){ if(ok(cur,cur.r,cur.c+1,cur.rot)) cur.c++; }

function rot(){
  const nr=(cur.rot+1)%4;
  for(const k of [0,-1,1,-2,2]){
    if(ok(cur,cur.r,cur.c+k,nr)){ cur.c+=k; cur.rot=nr; return; }
  }
}

function soft(){
  if(ok(cur,cur.r+1,cur.c,cur.rot)){ cur.r++; score+=1; }
  else lock();
  ui();
}

function hard(){
  let d=0;
  while(ok(cur,cur.r+1,cur.c,cur.rot)){ cur.r++; d++; }
  score+=d*2; lock(); ui();
}

function lock(){
  cells(cur,cur.r,cur.c,cur.rot).forEach(([r,c])=>{ board[r][c]=COLORS[cur.k]; });
  clear(); spawn();
}

function clear(){
  let n=0;
  for(let r=ROWS-1;r>=0;r--){
    if(board[r].every(x=>x)){ board.splice(r,1); board.unshift(Array(COLS).fill(0)); n++; r++; }
  }
  if(!n) return;
  score+=[0,100,300,500,800][n]*level;
  lines+=n; level=Math.floor(lines/10)+1; ui();
}

function speed(){ return Math.max(80,800-(level-1)*70); }

function loop(ts){
  if(!running||paused) return;
  const d=ts-(last||ts); last=ts; accum+=d;
  if(accum>=speed()){
    accum=0;
    if(ok(cur,cur.r+1,cur.c,cur.rot)) cur.r++;
    else lock();
  }
  draw(); raf=requestAnimationFrame(loop);
}

// ---- draw ----
function drawBlock(cx2, col, row, color, alpha=1){
  cx2.globalAlpha=alpha;
  // flat block, just a slight inner border effect
  cx2.fillStyle=color;
  cx2.fillRect(col*B+1, row*B+1, B-2, B-2);
  // top-left lighter edge
  cx2.fillStyle='rgba(255,255,255,0.2)';
  cx2.fillRect(col*B+1, row*B+1, B-2, 2);
  cx2.fillRect(col*B+1, row*B+1, 2, B-2);
  // bottom-right darker edge
  cx2.fillStyle='rgba(0,0,0,0.12)';
  cx2.fillRect(col*B+1, row*B+B-3, B-2, 2);
  cx2.fillRect(col*B+B-3, row*B+1, 2, B-2);
  cx2.globalAlpha=1;
}

function ghost(){
  let gr=cur.r;
  while(ok(cur,gr+1,cur.c,cur.rot)) gr++;
  cells(cur,gr,cur.c,cur.rot).forEach(([r,c])=>{
    cx.globalAlpha=0.12;
    cx.fillStyle=COLORS[cur.k];
    cx.fillRect(c*B+1,r*B+1,B-2,B-2);
    cx.globalAlpha=1;
  });
}

function draw(){
  cx.fillStyle='#f8f5f0';
  cx.fillRect(0,0,cv.width,cv.height);

  // grid lines (faint)
  cx.strokeStyle='rgba(0,0,0,0.04)';
  cx.lineWidth=1;
  for(let r=0;r<ROWS;r++) for(let c=0;c<COLS;c++) cx.strokeRect(c*B,r*B,B,B);

  for(let r=0;r<ROWS;r++) for(let c=0;c<COLS;c++)
    if(board[r][c]) drawBlock(cx,c,r,board[r][c]);

  if(cur){ ghost(); cells(cur,cur.r,cur.c,cur.rot).forEach(([r,c])=>drawBlock(cx,c,r,COLORS[cur.k])); }
}

function drawNext(){
  nx.fillStyle='#f8f5f0';
  nx.fillRect(0,0,nc.width,nc.height);
  if(!nxt) return;
  const sh=SHAPES[nxt.k][0];
  const minR=Math.min(...sh.map(([r])=>r)), minC=Math.min(...sh.map(([,c])=>c));
  const maxR=Math.max(...sh.map(([r])=>r)), maxC=Math.max(...sh.map(([,c])=>c));
  const SZ=18;
  const ox=Math.floor((nc.width-(maxC-minC+1)*SZ)/2);
  const oy=Math.floor((nc.height-(maxR-minR+1)*SZ)/2);
  sh.forEach(([r,c])=>{
    const x=ox+(c-minC)*SZ, y=oy+(r-minR)*SZ;
    nx.fillStyle=COLORS[nxt.k];
    nx.fillRect(x+1,y+1,SZ-2,SZ-2);
    nx.fillStyle='rgba(255,255,255,0.2)';
    nx.fillRect(x+1,y+1,SZ-2,2);
    nx.fillRect(x+1,y+1,2,SZ-2);
  });
}

function ui(){
  document.getElementById('disp-score').textContent=score.toLocaleString();
  document.getElementById('disp-level').textContent=level;
  document.getElementById('disp-lines').textContent=lines;
  const hs=Math.max(score,parseInt(localStorage.getItem('tths')||'0'));
  document.getElementById('disp-hs').textContent=hs.toLocaleString();
}

function show(id){
  ['ov-start','ov-over','ov-pause'].forEach(o=>
    document.getElementById(o).classList.toggle('off', o!==id));
}
function hideAll(){
  ['ov-start','ov-over','ov-pause'].forEach(o=>document.getElementById(o).classList.add('off'));
}

function start(){
  cancelAnimationFrame(raf);
  init(); running=true; last=null;
  hideAll(); raf=requestAnimationFrame(loop);
}

function over(){
  running=false; cancelAnimationFrame(raf);
  const prev=parseInt(localStorage.getItem('tths')||'0');
  if(score>prev) localStorage.setItem('tths',score);
  document.getElementById('final-score').textContent=score.toLocaleString();
  ui(); show('ov-over');
}

function pause(){
  if(!running) return;
  paused=!paused;
  if(paused){ show('ov-pause'); }
  else { hideAll(); last=null; raf=requestAnimationFrame(loop); }
}

document.addEventListener('keydown',e=>{
  if(!running||paused){ if(e.key==='p'||e.key==='P') pause(); return; }
  switch(e.key){
    case 'ArrowLeft':  e.preventDefault(); left();  break;
    case 'ArrowRight': e.preventDefault(); right(); break;
    case 'ArrowDown':  e.preventDefault(); soft();  break;
    case 'ArrowUp':    e.preventDefault(); rot();   break;
    case ' ':          e.preventDefault(); hard();  break;
    case 'p': case 'P': pause(); break;
  }
  draw();
});

document.getElementById('ml').onclick=()=>{ if(running&&!paused){left();draw();} };
document.getElementById('mr').onclick=()=>{ if(running&&!paused){right();draw();} };
document.getElementById('md').onclick=()=>{ if(running&&!paused){soft();draw();} };
document.getElementById('mrot').onclick=()=>{ if(running&&!paused){rot();draw();} };
document.getElementById('mdrop').onclick=()=>{ if(running&&!paused){hard();draw();} };

document.getElementById('btn-start').onclick=start;
document.getElementById('btn-restart').onclick=start;

show('ov-start');
drawNext();
draw();
ui();
</script>
</body>
</html>