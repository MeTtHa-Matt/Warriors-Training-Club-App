<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>SNAKE_.EXE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg: #060a08;
    --bg-panel: #0b120e;
    --grid-line: #12241a;
    --phosphor: #3dff9a;
    --phosphor-dim: #1c6b45;
    --amber: #ffb000;
    --ink: #cdf5df;
    --danger: #ff4d5e;
    --font-display: 'Press Start 2P', monospace;
    --font-mono: 'JetBrains Mono', monospace;
  }

  *{ box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

  html, body{
    margin:0; padding:0; height:100%;
    background: var(--bg);
    color: var(--ink);
    font-family: var(--font-mono);
    overscroll-behavior: none;
    touch-action: none;
    user-select: none;
  }

  body{
    display:flex;
    flex-direction:column;
    align-items:center;
    min-height:100vh;
    min-height:100dvh;
    padding: 14px 12px calc(14px + env(safe-area-inset-bottom));
    gap: 12px;
    background:
      radial-gradient(ellipse at 50% -10%, rgba(61,255,154,0.08), transparent 60%),
      var(--bg);
  }

  /* ---------- CRT scanline overlay ---------- */
  .crt-overlay{
    position: fixed; inset:0;
    pointer-events:none;
    z-index: 50;
    background: repeating-linear-gradient(
      to bottom,
      rgba(0,0,0,0) 0px,
      rgba(0,0,0,0) 1px,
      rgba(0,0,0,0.18) 2px,
      rgba(0,0,0,0.18) 3px
    );
    mix-blend-mode: multiply;
    opacity:0.5;
  }
  .crt-vignette{
    position: fixed; inset:0;
    pointer-events:none;
    z-index: 51;
    box-shadow: inset 0 0 18vw rgba(0,0,0,0.85);
  }

  /* ---------- Header ---------- */
  header{
    width:100%;
    max-width: 480px;
    text-align:center;
  }
  .title{
    font-family: var(--font-display);
    font-size: clamp(14px, 4.6vw, 22px);
    color: var(--phosphor);
    letter-spacing: 2px;
    text-shadow: 0 0 6px rgba(61,255,154,0.65), 0 0 18px rgba(61,255,154,0.25);
    margin: 4px 0 2px;
  }
  .subtitle{
    font-size: 10px;
    color: var(--phosphor-dim);
    letter-spacing: 3px;
    text-transform: uppercase;
  }

  /* ---------- Scoreboard ---------- */
  .scoreboard{
    width:100%;
    max-width: 480px;
    display:flex;
    justify-content: space-between;
    gap: 10px;
  }
  .score-box{
    flex:1;
    background: var(--bg-panel);
    border: 1px solid var(--phosphor-dim);
    padding: 8px 10px;
    text-align:center;
    position: relative;
  }
  .score-box::before{
    content:"";
    position:absolute; inset:0;
    box-shadow: inset 0 0 10px rgba(61,255,154,0.06);
    pointer-events:none;
  }
  .score-label{
    font-size: 9px;
    letter-spacing: 2px;
    color: var(--phosphor-dim);
    margin-bottom: 4px;
  }
  .score-value{
    font-family: var(--font-display);
    font-size: clamp(14px, 4vw, 20px);
    color: var(--amber);
    text-shadow: 0 0 8px rgba(255,176,0,0.5);
  }
  .score-value.you{
    color: var(--phosphor);
    text-shadow: 0 0 8px rgba(61,255,154,0.5);
  }

  /* ---------- Game board ---------- */
  .board-wrap{
    position: relative;
    width: 100%;
    max-width: 480px;
    aspect-ratio: 1 / 1;
    background: var(--bg-panel);
    border: 2px solid var(--phosphor-dim);
    box-shadow:
      0 0 0 1px #000,
      0 0 24px rgba(61,255,154,0.08),
      inset 0 0 40px rgba(0,0,0,0.6);
  }
  canvas{
    display:block;
    width:100%;
    height:100%;
    image-rendering: pixelated;
  }

  .overlay-msg{
    position:absolute; inset:0;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap: 14px;
    background: rgba(6,10,8,0.86);
    backdrop-filter: blur(1px);
    text-align:center;
    padding: 20px;
    opacity:0;
    pointer-events:none;
    transition: opacity 0.25s ease;
  }
  .overlay-msg.active{
    opacity:1;
    pointer-events:auto;
  }
  .overlay-title{
    font-family: var(--font-display);
    font-size: clamp(16px, 5vw, 26px);
    color: var(--phosphor);
    text-shadow: 0 0 10px rgba(61,255,154,0.7);
    letter-spacing: 2px;
  }
  .overlay-title.dead{
    color: var(--danger);
    text-shadow: 0 0 10px rgba(255,77,94,0.7);
  }
  .overlay-sub{
    font-size: 11px;
    color: var(--ink);
    line-height: 1.7;
    max-width: 280px;
  }
  .overlay-sub b{ color: var(--amber); }

  .btn{
    font-family: var(--font-display);
    font-size: 11px;
    color: var(--bg);
    background: var(--phosphor);
    border: none;
    padding: 12px 20px;
    letter-spacing: 1px;
    cursor: pointer;
    box-shadow: 0 0 14px rgba(61,255,154,0.5);
    transition: transform 0.08s ease;
  }
  .btn:active{ transform: scale(0.95); }

  /* ---------- Controls ---------- */
  .controls{
    width:100%;
    max-width: 480px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap: 14px;
  }
  .dpad{
    display:grid;
    grid-template-columns: 52px 52px 52px;
    grid-template-rows: 52px 52px 52px;
    gap: 4px;
  }
  .dpad button{
    background: var(--bg-panel);
    border: 1px solid var(--phosphor-dim);
    color: var(--phosphor);
    font-size: 18px;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer;
    touch-action: none;
  }
  .dpad button:active{
    background: var(--phosphor-dim);
    box-shadow: 0 0 10px rgba(61,255,154,0.4) inset;
  }
  .dpad .up{ grid-column:2; grid-row:1; }
  .dpad .left{ grid-column:1; grid-row:2; }
  .dpad .center{ grid-column:2; grid-row:2; border-color: transparent; background: transparent; cursor:default; }
  .dpad .right{ grid-column:3; grid-row:2; }
  .dpad .down{ grid-column:2; grid-row:3; }

  .side-controls{
    display:flex;
    flex-direction:column;
    gap: 10px;
    flex:1;
    max-width: 160px;
  }
  .side-btn{
    font-family: var(--font-mono);
    font-size: 10px;
    letter-spacing: 1px;
    color: var(--ink);
    background: var(--bg-panel);
    border: 1px solid var(--phosphor-dim);
    padding: 10px 8px;
    cursor:pointer;
    text-transform: uppercase;
  }
  .side-btn:active{ background: var(--phosphor-dim); }

  footer{
    font-size: 9px;
    color: var(--phosphor-dim);
    letter-spacing: 1px;
    text-align:center;
    max-width: 480px;
  }

  @media (min-width: 560px){
    .dpad{
      grid-template-columns: 58px 58px 58px;
      grid-template-rows: 58px 58px 58px;
    }
  }

  @media (prefers-reduced-motion: reduce){
    .btn, .dpad button, .side-btn{ transition:none; }
  }
</style>
</head>
<body>

  <div class="crt-overlay"></div>
  <div class="crt-vignette"></div>

  <header>
    <div class="title">SNAKE_.EXE</div>
    <div class="subtitle">terminal edition</div>
  </header>

  <div class="scoreboard">
    <div class="score-box">
      <div class="score-label">SCORE</div>
      <div class="score-value you" id="scoreValue">0</div>
    </div>
    <div class="score-box">
      <div class="score-label">MEILLEUR</div>
      <div class="score-value" id="bestValue">0</div>
    </div>
    <div class="score-box">
      <div class="score-label">VITESSE</div>
      <div class="score-value" id="speedValue">1</div>
    </div>
  </div>

  <div class="board-wrap">
    <canvas id="board"></canvas>

    <div class="overlay-msg active" id="startOverlay">
      <div class="overlay-title">PRÊT ?</div>
      <div class="overlay-sub">Glisse sur l'écran ou utilise les flèches / le pavé directionnel pour te déplacer.<br><br>Mange les <b>points ambre</b> pour grandir. Évite les murs et ta propre queue.</div>
      <button class="btn" id="startBtn">JOUER</button>
    </div>

    <div class="overlay-msg" id="pauseOverlay">
      <div class="overlay-title">PAUSE</div>
      <button class="btn" id="resumeBtn">REPRENDRE</button>
    </div>

    <div class="overlay-msg" id="gameOverOverlay">
      <div class="overlay-title dead">GAME OVER</div>
      <div class="overlay-sub">Score final : <b id="finalScore">0</b></div>
      <button class="btn" id="retryBtn">REJOUER</button>
    </div>
  </div>

  <div class="controls">
    <div class="dpad">
      <button class="up" data-dir="up" aria-label="Haut">▲</button>
      <button class="left" data-dir="left" aria-label="Gauche">◀</button>
      <div class="center"></div>
      <button class="right" data-dir="right" aria-label="Droite">▶</button>
      <button class="down" data-dir="down" aria-label="Bas">▼</button>
    </div>
    <div class="side-controls">
      <button class="side-btn" id="pauseBtn">⏸ PAUSE</button>
      <button class="side-btn" id="restartBtn">↻ RESTART</button>
    </div>
  </div>

  <footer>SWIPE • FLÈCHES • PAVÉ TACTILE — AUCUNE DONNÉE SAUVEGARDÉE ENTRE SESSIONS</footer>

<script>
(function(){
  "use strict";

  // ---------- Setup ----------
  const canvas = document.getElementById('board');
  const ctx = canvas.getContext('2d');

  const GRID_SIZE = 18; // cells per side
  let cellPx = 0;

  const scoreValueEl = document.getElementById('scoreValue');
  const bestValueEl = document.getElementById('bestValue');
  const speedValueEl = document.getElementById('speedValue');
  const finalScoreEl = document.getElementById('finalScore');

  const startOverlay = document.getElementById('startOverlay');
  const pauseOverlay = document.getElementById('pauseOverlay');
  const gameOverOverlay = document.getElementById('gameOverOverlay');

  let bestScore = 0;

  // ---------- Game state ----------
  let snake, dir, nextDir, food, score, speedLevel, tickMs, timer, running, paused;

  function resetState(){
    const mid = Math.floor(GRID_SIZE/2);
    snake = [
      {x: mid-1, y: mid},
      {x: mid-2, y: mid},
      {x: mid-3, y: mid}
    ];
    dir = {x:1, y:0};
    nextDir = {x:1, y:0};
    score = 0;
    speedLevel = 1;
    tickMs = 150;
    running = false;
    paused = false;
    placeFood();
    updateHud();
  }

  function placeFood(){
    let candidate;
    do{
      candidate = {
        x: Math.floor(Math.random()*GRID_SIZE),
        y: Math.floor(Math.random()*GRID_SIZE)
      };
    } while(snake.some(s => s.x===candidate.x && s.y===candidate.y));
    food = candidate;
  }

  function updateHud(){
    scoreValueEl.textContent = score;
    bestValueEl.textContent = bestScore;
    speedValueEl.textContent = speedLevel;
  }

  // ---------- Sizing (responsive, mobile-first) ----------
  function resizeCanvas(){
    const wrap = canvas.parentElement;
    const size = wrap.clientWidth;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = size * dpr;
    canvas.height = size * dpr;
    ctx.setTransform(dpr,0,0,dpr,0,0);
    cellPx = size / GRID_SIZE;
    draw();
  }
  window.addEventListener('resize', resizeCanvas);

  // ---------- Drawing ----------
  function draw(){
    const size = GRID_SIZE * cellPx;

    // background
    ctx.fillStyle = '#0b120e';
    ctx.fillRect(0,0,size,size);

    // grid
    ctx.strokeStyle = 'rgba(61,255,154,0.06)';
    ctx.lineWidth = 1;
    for(let i=1;i<GRID_SIZE;i++){
      const p = i*cellPx;
      ctx.beginPath(); ctx.moveTo(p,0); ctx.lineTo(p,size); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(0,p); ctx.lineTo(size,p); ctx.stroke();
    }

    // food (pulsing amber dot)
    const pulse = 0.7 + 0.3*Math.sin(Date.now()/180);
    const fx = food.x*cellPx + cellPx/2;
    const fy = food.y*cellPx + cellPx/2;
    const r = (cellPx/2 - 2) * pulse;
    ctx.fillStyle = '#ffb000';
    ctx.shadowColor = 'rgba(255,176,0,0.9)';
    ctx.shadowBlur = 10;
    ctx.beginPath();
    ctx.arc(fx, fy, Math.max(r,3), 0, Math.PI*2);
    ctx.fill();
    ctx.shadowBlur = 0;

    // snake
    snake.forEach((seg, i) => {
      const isHead = i===0;
      ctx.fillStyle = isHead ? '#8dffc7' : '#3dff9a';
      ctx.shadowColor = 'rgba(61,255,154,0.7)';
      ctx.shadowBlur = isHead ? 8 : 3;
      const pad = isHead ? 1 : 1.5;
      ctx.fillRect(
        seg.x*cellPx + pad,
        seg.y*cellPx + pad,
        cellPx - pad*2,
        cellPx - pad*2
      );
    });
    ctx.shadowBlur = 0;
  }

  // ---------- Game loop ----------
  function tick(){
    dir = nextDir;
    const head = {x: snake[0].x + dir.x, y: snake[0].y + dir.y};

    // wall collision
    if(head.x < 0 || head.x >= GRID_SIZE || head.y < 0 || head.y >= GRID_SIZE){
      return gameOver();
    }
    // self collision
    if(snake.some(s => s.x===head.x && s.y===head.y)){
      return gameOver();
    }

    snake.unshift(head);

    if(head.x === food.x && head.y === food.y){
      score += 10;
      if(score > bestScore) bestScore = score;
      placeFood();
      // speed up every 50 points
      const newLevel = 1 + Math.floor(score/50);
      if(newLevel !== speedLevel){
        speedLevel = newLevel;
        tickMs = Math.max(60, 150 - (speedLevel-1)*14);
        restartTimer();
      }
    } else {
      snake.pop();
    }

    updateHud();
    draw();
  }

  function restartTimer(){
    clearInterval(timer);
    if(running && !paused){
      timer = setInterval(tick, tickMs);
    }
  }

  function startGame(){
    resetState();
    running = true;
    paused = false;
    hideOverlays();
    resizeCanvas();
    clearInterval(timer);
    timer = setInterval(tick, tickMs);
  }

  function gameOver(){
    running = false;
    clearInterval(timer);
    finalScoreEl.textContent = score;
    showOverlay(gameOverOverlay);
  }

  function togglePause(){
    if(!running) return;
    paused = !paused;
    if(paused){
      clearInterval(timer);
      showOverlay(pauseOverlay);
    } else {
      hideOverlays();
      timer = setInterval(tick, tickMs);
    }
  }

  function hideOverlays(){
    [startOverlay, pauseOverlay, gameOverOverlay].forEach(o => o.classList.remove('active'));
  }
  function showOverlay(el){
    hideOverlays();
    el.classList.add('active');
  }

  // ---------- Direction input ----------
  function setDirection(newDir){
    // prevent reversing directly into itself
    if(newDir.x === -dir.x && newDir.y === -dir.y) return;
    if(newDir.x === dir.x && newDir.y === dir.y) return;
    nextDir = newDir;
  }

  const DIRS = {
    up: {x:0, y:-1},
    down: {x:0, y:1},
    left: {x:-1, y:0},
    right: {x:1, y:0}
  };

  document.querySelectorAll('.dpad button[data-dir]').forEach(btn => {
    btn.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      setDirection(DIRS[btn.dataset.dir]);
    });
  });

  window.addEventListener('keydown', (e) => {
    switch(e.key){
      case 'ArrowUp': case 'w': case 'W': setDirection(DIRS.up); e.preventDefault(); break;
      case 'ArrowDown': case 's': case 'S': setDirection(DIRS.down); e.preventDefault(); break;
      case 'ArrowLeft': case 'a': case 'A': setDirection(DIRS.left); e.preventDefault(); break;
      case 'ArrowRight': case 'd': case 'D': setDirection(DIRS.right); e.preventDefault(); break;
      case ' ': togglePause(); e.preventDefault(); break;
    }
  });

  // ---------- Swipe input ----------
  let touchStart = null;
  const boardWrap = document.querySelector('.board-wrap');
  boardWrap.addEventListener('touchstart', (e) => {
    const t = e.changedTouches[0];
    touchStart = {x: t.clientX, y: t.clientY};
  }, {passive:true});

  boardWrap.addEventListener('touchend', (e) => {
    if(!touchStart) return;
    const t = e.changedTouches[0];
    const dx = t.clientX - touchStart.x;
    const dy = t.clientY - touchStart.y;
    const absX = Math.abs(dx), absY = Math.abs(dy);
    const THRESHOLD = 24;
    if(Math.max(absX,absY) < THRESHOLD){ touchStart = null; return; }
    if(absX > absY){
      setDirection(dx > 0 ? DIRS.right : DIRS.left);
    } else {
      setDirection(dy > 0 ? DIRS.down : DIRS.up);
    }
    touchStart = null;
  }, {passive:true});

  // ---------- Buttons ----------
  document.getElementById('startBtn').addEventListener('click', startGame);
  document.getElementById('retryBtn').addEventListener('click', startGame);
  document.getElementById('resumeBtn').addEventListener('click', togglePause);
  document.getElementById('pauseBtn').addEventListener('click', togglePause);
  document.getElementById('restartBtn').addEventListener('click', startGame);

  // ---------- Init ----------
  resetState();
  resizeCanvas();
  draw();

  // gentle idle animation of food while on start screen
  setInterval(() => { if(!running) draw(); }, 100);

})();
</script>
</body>
</html>
