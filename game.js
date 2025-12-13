/**
 * 3D Chrome Dino Game
 * A Three.js implementation of the classic Chrome dinosaur game
 */

(function() {
  'use strict';

  const canvas = document.getElementById('game-canvas');
  if (!canvas) return;

  let scene, camera, renderer;
  let dino, ground;
  let obstacles = [];
  let particles = [];
  let stars = [];
  let isInitialized = false;
  let gameStarted = false;
  let gameOver = false;
  let score = 0;
  let highScore = parseInt(localStorage.getItem('dinoHighScore')) || 0;
  let speed = 0.15;
  let baseSpeed = 0.15;
  let jumpVelocity = 0;
  let isJumping = false;
  let isDucking = false;
  let gravity = 0.015;
  let groundY = -2;
  let obstacleTimer = 0;
  let lastTime = 0;
  let colors;

  // Ground elements
  let groundLines = [];
  let groundEdges = [];

  // UI Elements
  const scoreEl = document.getElementById('game-score');
  const highScoreEl = document.getElementById('game-high-score');
  const startScreen = document.getElementById('game-start');
  const gameOverScreen = document.getElementById('game-over');
  const finalScoreEl = document.getElementById('final-score');

  function getThemeColors() {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    return {
      primary: isDark ? 0x3b82f6 : 0x2563eb,
      secondary: isDark ? 0x8b5cf6 : 0x7c3aed,
      dino: isDark ? 0x22d3ee : 0x0891b2,
      ground: isDark ? 0x1e1e24 : 0xe4e4e7,
      groundLine: isDark ? 0x3b82f6 : 0x2563eb,
      obstacle: isDark ? 0xf43f5e : 0xe11d48,
      particle: isDark ? 0x22d3ee : 0x0891b2,
      sky: isDark ? 0x0a0a0b : 0xfafafa
    };
  }

  function init() {
    if (isInitialized) return;
    isInitialized = true;

    colors = getThemeColors();

    // Scene setup
    scene = new THREE.Scene();

    const aspect = canvas.clientWidth / canvas.clientHeight;
    camera = new THREE.PerspectiveCamera(60, aspect, 0.1, 1000);
    camera.position.set(0, 2, 12);
    camera.lookAt(0, 0, 0);

    renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      alpha: true
    });
    renderer.setSize(canvas.clientWidth, canvas.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    // Create dino
    createDino();

    // Create ground
    createGround();

    // Create background stars
    createStars();

    // Lighting
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
    directionalLight.position.set(5, 10, 5);
    scene.add(directionalLight);

    // Event listeners
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('keyup', onKeyUp);

    // Pointer events work for both mouse and touch
    canvas.addEventListener('mousedown', onPointerDown);
    canvas.addEventListener('mouseup', onPointerUp);
    canvas.addEventListener('touchstart', onPointerDown, { passive: true });
    canvas.addEventListener('touchend', onPointerUp, { passive: true });
    window.addEventListener('resize', onResize);

    // Add handlers to overlays for mobile support
    if (startScreen) {
      startScreen.addEventListener('mousedown', onPointerDown);
      startScreen.addEventListener('mouseup', onPointerUp);
      startScreen.addEventListener('touchstart', onPointerDown, { passive: true });
      startScreen.addEventListener('touchend', onPointerUp, { passive: true });
    }
    if (gameOverScreen) {
      gameOverScreen.addEventListener('mousedown', onPointerDown);
      gameOverScreen.addEventListener('mouseup', onPointerUp);
      gameOverScreen.addEventListener('touchstart', onPointerDown, { passive: true });
      gameOverScreen.addEventListener('touchend', onPointerUp, { passive: true });
    }

    // Theme observer
    const themeObserver = new MutationObserver(onThemeChange);
    themeObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme']
    });

    // Update high score display
    if (highScoreEl) highScoreEl.textContent = highScore;

    animate(0);
  }

  function createDino() {
    const dinoGroup = new THREE.Group();

    // Body
    const bodyGeom = new THREE.BoxGeometry(1.2, 1.4, 0.8);
    const dinoMat = new THREE.MeshPhongMaterial({
      color: colors.dino,
      emissive: colors.dino,
      emissiveIntensity: 0.2,
      flatShading: true
    });
    const body = new THREE.Mesh(bodyGeom, dinoMat);
    body.position.y = 0.7;
    dinoGroup.add(body);

    // Head
    const headGeom = new THREE.BoxGeometry(0.9, 0.7, 0.7);
    const head = new THREE.Mesh(headGeom, dinoMat);
    head.position.set(0.5, 1.5, 0);
    dinoGroup.add(head);

    // Eye
    const eyeGeom = new THREE.SphereGeometry(0.1, 8, 8);
    const eyeMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
    const eye = new THREE.Mesh(eyeGeom, eyeMat);
    eye.position.set(0.85, 1.6, 0.2);
    dinoGroup.add(eye);

    // Pupil
    const pupilGeom = new THREE.SphereGeometry(0.05, 8, 8);
    const pupilMat = new THREE.MeshBasicMaterial({ color: 0x000000 });
    const pupil = new THREE.Mesh(pupilGeom, pupilMat);
    pupil.position.set(0.92, 1.6, 0.22);
    dinoGroup.add(pupil);

    // Legs
    const legGeom = new THREE.BoxGeometry(0.3, 0.6, 0.3);
    const legL = new THREE.Mesh(legGeom, dinoMat);
    legL.position.set(-0.2, -0.1, 0.2);
    dinoGroup.add(legL);

    const legR = new THREE.Mesh(legGeom, dinoMat);
    legR.position.set(-0.2, -0.1, -0.2);
    dinoGroup.add(legR);

    // Tail
    const tailGeom = new THREE.BoxGeometry(1, 0.5, 0.4);
    const tail = new THREE.Mesh(tailGeom, dinoMat);
    tail.position.set(-1, 0.8, 0);
    tail.rotation.z = -0.2;
    dinoGroup.add(tail);

    // Arms (small T-Rex arms)
    const armGeom = new THREE.BoxGeometry(0.2, 0.4, 0.2);
    const armL = new THREE.Mesh(armGeom, dinoMat);
    armL.position.set(0.5, 0.6, 0.4);
    armL.rotation.z = 0.5;
    dinoGroup.add(armL);

    const armR = new THREE.Mesh(armGeom, dinoMat);
    armR.position.set(0.5, 0.6, -0.4);
    armR.rotation.z = 0.5;
    dinoGroup.add(armR);

    dinoGroup.position.set(-4, groundY + 0.4, 0);
    dino = dinoGroup;
    dino.userData = { legL, legR, head, baseY: groundY + 0.4 };
    scene.add(dino);
  }

  function createGround() {
    // Main ground plane - a sleek dark surface
    const groundGeom = new THREE.PlaneGeometry(100, 12);
    const groundMat = new THREE.MeshPhongMaterial({
      color: colors.ground,
      side: THREE.DoubleSide
    });
    ground = new THREE.Mesh(groundGeom, groundMat);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = groundY - 0.4;
    ground.position.z = 0;
    scene.add(ground);

    // Create scrolling ground lines (like a road)
    const lineMat = new THREE.MeshBasicMaterial({
      color: colors.groundLine,
      transparent: true,
      opacity: 0.6
    });

    // Center dashed lines
    for (let i = 0; i < 20; i++) {
      const lineGeom = new THREE.BoxGeometry(2, 0.05, 0.15);
      const line = new THREE.Mesh(lineGeom, lineMat.clone());
      line.position.set(-30 + i * 4, groundY - 0.35, 0);
      groundLines.push(line);
      scene.add(line);
    }

    // Edge lines (continuous glowing lines on sides)
    const edgeMat = new THREE.MeshBasicMaterial({
      color: colors.primary,
      transparent: true,
      opacity: 0.8
    });

    // Left edge
    const leftEdgeGeom = new THREE.BoxGeometry(100, 0.08, 0.1);
    const leftEdge = new THREE.Mesh(leftEdgeGeom, edgeMat);
    leftEdge.position.set(0, groundY - 0.35, -4);
    scene.add(leftEdge);
    groundEdges.push(leftEdge);

    // Right edge
    const rightEdge = new THREE.Mesh(leftEdgeGeom, edgeMat.clone());
    rightEdge.position.set(0, groundY - 0.35, 4);
    scene.add(rightEdge);
    groundEdges.push(rightEdge);

    // Add some lane markers (short perpendicular lines)
    const markerMat = new THREE.MeshBasicMaterial({
      color: colors.secondary,
      transparent: true,
      opacity: 0.3
    });

    for (let i = 0; i < 15; i++) {
      const markerGeom = new THREE.BoxGeometry(0.1, 0.05, 8);
      const marker = new THREE.Mesh(markerGeom, markerMat.clone());
      marker.position.set(-35 + i * 6, groundY - 0.38, 0);
      groundLines.push(marker);
      scene.add(marker);
    }

    // Add subtle glow effect at the edges
    const glowMat = new THREE.MeshBasicMaterial({
      color: colors.primary,
      transparent: true,
      opacity: 0.15
    });

    const glowGeom = new THREE.PlaneGeometry(100, 1.5);
    const leftGlow = new THREE.Mesh(glowGeom, glowMat);
    leftGlow.rotation.x = -Math.PI / 2;
    leftGlow.position.set(0, groundY - 0.39, -4.5);
    scene.add(leftGlow);

    const rightGlow = new THREE.Mesh(glowGeom, glowMat.clone());
    rightGlow.rotation.x = -Math.PI / 2;
    rightGlow.position.set(0, groundY - 0.39, 4.5);
    scene.add(rightGlow);
  }

  function createStars() {
    const starGeom = new THREE.BufferGeometry();
    const starCount = 200;
    const positions = new Float32Array(starCount * 3);

    for (let i = 0; i < starCount * 3; i += 3) {
      positions[i] = (Math.random() - 0.5) * 80;
      positions[i + 1] = Math.random() * 20 + 5;
      positions[i + 2] = (Math.random() - 0.5) * 40 - 20;
    }

    starGeom.setAttribute('position', new THREE.BufferAttribute(positions, 3));

    const starMat = new THREE.PointsMaterial({
      color: colors.primary,
      size: 0.1,
      transparent: true,
      opacity: 0.6
    });

    const starField = new THREE.Points(starGeom, starMat);
    stars.push(starField);
    scene.add(starField);
  }

  function createObstacle() {
    const type = Math.random();
    let obstacle;

    if (type < 0.6) {
      // Cactus
      obstacle = createCactus();
    } else if (type < 0.85) {
      // Double cactus
      obstacle = createDoubleCactus();
    } else {
      // Flying pterodactyl
      obstacle = createPterodactyl();
    }

    obstacle.position.x = 20;
    obstacles.push(obstacle);
    scene.add(obstacle);
  }

  function createCactus() {
    const group = new THREE.Group();
    const mat = new THREE.MeshPhongMaterial({
      color: colors.obstacle,
      emissive: colors.obstacle,
      emissiveIntensity: 0.3,
      flatShading: true
    });

    // Main trunk
    const trunkGeom = new THREE.BoxGeometry(0.5, 2 + Math.random(), 0.5);
    const trunk = new THREE.Mesh(trunkGeom, mat);
    trunk.position.y = 1;
    group.add(trunk);

    // Arms
    if (Math.random() > 0.3) {
      const armGeom = new THREE.BoxGeometry(0.4, 0.8, 0.4);
      const armL = new THREE.Mesh(armGeom, mat);
      armL.position.set(0.4, 1.2 + Math.random() * 0.5, 0);
      group.add(armL);

      const connectorL = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.3, 0.3), mat);
      connectorL.position.set(0.2, 1.2 + Math.random() * 0.3, 0);
      group.add(connectorL);
    }

    if (Math.random() > 0.5) {
      const armGeom = new THREE.BoxGeometry(0.4, 0.6, 0.4);
      const armR = new THREE.Mesh(armGeom, mat);
      armR.position.set(-0.4, 1.5 + Math.random() * 0.3, 0);
      group.add(armR);
    }

    group.position.y = groundY;
    group.userData = { type: 'cactus', width: 0.8, height: 2.5, passed: false };
    return group;
  }

  function createDoubleCactus() {
    const group = new THREE.Group();

    const cactus1 = createCactus();
    cactus1.position.x = -0.6;
    cactus1.position.y = 0;
    group.add(cactus1);

    const cactus2 = createCactus();
    cactus2.position.x = 0.6;
    cactus2.position.y = 0;
    group.add(cactus2);

    group.position.y = groundY;
    group.userData = { type: 'doubleCactus', width: 2, height: 2.5, passed: false };
    return group;
  }

  function createPterodactyl() {
    const group = new THREE.Group();
    const mat = new THREE.MeshPhongMaterial({
      color: colors.secondary,
      emissive: colors.secondary,
      emissiveIntensity: 0.3,
      flatShading: true
    });

    // Body
    const bodyGeom = new THREE.ConeGeometry(0.3, 1.2, 4);
    const body = new THREE.Mesh(bodyGeom, mat);
    body.rotation.z = Math.PI / 2;
    group.add(body);

    // Wings
    const wingGeom = new THREE.BoxGeometry(0.1, 1.5, 0.8);
    const wingL = new THREE.Mesh(wingGeom, mat);
    wingL.position.set(0, 0.5, 0);
    wingL.name = 'wingL';
    group.add(wingL);

    const wingR = new THREE.Mesh(wingGeom, mat);
    wingR.position.set(0, -0.5, 0);
    wingR.name = 'wingR';
    group.add(wingR);

    // Head/beak
    const headGeom = new THREE.ConeGeometry(0.15, 0.5, 4);
    const head = new THREE.Mesh(headGeom, mat);
    head.rotation.z = Math.PI / 2;
    head.position.x = 0.7;
    group.add(head);

    // Random height (high or low)
    const flyHeight = Math.random() > 0.5 ? groundY + 3 : groundY + 1.2;
    group.position.y = flyHeight;
    group.userData = { type: 'pterodactyl', width: 1.2, height: 1.5, wingL, wingR, wingPhase: 0, passed: false };
    return group;
  }

  function createJumpParticles() {
    const particleCount = 15;
    for (let i = 0; i < particleCount; i++) {
      const geom = new THREE.SphereGeometry(0.08, 6, 6);
      const mat = new THREE.MeshBasicMaterial({
        color: colors.particle,
        transparent: true,
        opacity: 1
      });
      const particle = new THREE.Mesh(geom, mat);
      particle.position.set(
        dino.position.x + (Math.random() - 0.5) * 1,
        dino.position.y + Math.random() * 0.5,
        (Math.random() - 0.5) * 1
      );
      particle.userData = {
        velocity: {
          x: (Math.random() - 0.5) * 0.2,
          y: Math.random() * 0.15,
          z: (Math.random() - 0.5) * 0.2
        },
        life: 1
      };
      particles.push(particle);
      scene.add(particle);
    }
  }

  function createDeathParticles() {
    const particleCount = 40;
    for (let i = 0; i < particleCount; i++) {
      const geom = new THREE.BoxGeometry(0.15, 0.15, 0.15);
      const mat = new THREE.MeshBasicMaterial({
        color: Math.random() > 0.5 ? colors.dino : colors.obstacle,
        transparent: true,
        opacity: 1
      });
      const particle = new THREE.Mesh(geom, mat);
      particle.position.copy(dino.position);
      particle.position.y += 1;
      particle.userData = {
        velocity: {
          x: (Math.random() - 0.5) * 0.4,
          y: Math.random() * 0.3,
          z: (Math.random() - 0.5) * 0.4
        },
        rotSpeed: {
          x: (Math.random() - 0.5) * 0.3,
          y: (Math.random() - 0.5) * 0.3,
          z: (Math.random() - 0.5) * 0.3
        },
        life: 1
      };
      particles.push(particle);
      scene.add(particle);
    }
  }

  function createScoreParticles() {
    // Create a burst of green/gold particles when scoring
    const particleCount = 8;
    for (let i = 0; i < particleCount; i++) {
      const geom = new THREE.SphereGeometry(0.06, 6, 6);
      const mat = new THREE.MeshBasicMaterial({
        color: 0x22c55e,
        transparent: true,
        opacity: 1
      });
      const particle = new THREE.Mesh(geom, mat);
      particle.position.set(
        dino.position.x + 1,
        dino.position.y + 1.5,
        (Math.random() - 0.5) * 0.5
      );
      particle.userData = {
        velocity: {
          x: 0.05 + Math.random() * 0.1,
          y: 0.1 + Math.random() * 0.15,
          z: (Math.random() - 0.5) * 0.1
        },
        life: 1
      };
      particles.push(particle);
      scene.add(particle);
    }
  }

  function jump() {
    if (!isJumping && !gameOver) {
      isJumping = true;
      jumpVelocity = 0.35;
      createJumpParticles();
    }
  }

  function onKeyDown(e) {
    if (e.code === 'Space' || e.code === 'ArrowUp') {
      e.preventDefault();
      if (!gameStarted) {
        startGame();
      } else if (gameOver) {
        restartGame();
      } else {
        jump();
      }
    }
    if (e.code === 'ArrowDown' && gameStarted && !gameOver) {
      e.preventDefault();
      isDucking = true;
      dino.scale.y = 0.5;
      dino.position.y = dino.userData.baseY - 0.5;
    }
  }

  function onKeyUp(e) {
    if (e.code === 'ArrowDown') {
      isDucking = false;
      dino.scale.y = 1;
      if (!isJumping) {
        dino.position.y = dino.userData.baseY;
      }
    }
  }

  function onPointerDown(e) {
    if (!gameStarted) {
      startGame();
      return;
    }
    if (gameOver) {
      restartGame();
      return;
    }
    // During gameplay: press to duck
    if (!isJumping) {
      isDucking = true;
      dino.scale.y = 0.5;
      dino.position.y = dino.userData.baseY - 0.5;
    }
  }

  function onPointerUp(e) {
    if (!gameStarted || gameOver) return;

    // Release to jump (if was ducking, stand up first then jump)
    if (isDucking) {
      isDucking = false;
      dino.scale.y = 1;
      dino.position.y = dino.userData.baseY;
    }
    jump();
  }

  function startGame() {
    gameStarted = true;
    gameOver = false;
    score = 0;
    speed = baseSpeed;
    if (startScreen) startScreen.style.display = 'none';
    if (gameOverScreen) gameOverScreen.style.display = 'none';
    if (scoreEl) scoreEl.textContent = '0';
  }

  function restartGame() {
    // Clear obstacles
    obstacles.forEach(obs => scene.remove(obs));
    obstacles = [];

    // Clear particles
    particles.forEach(p => scene.remove(p));
    particles = [];

    // Reset dino
    dino.position.y = dino.userData.baseY;
    dino.scale.y = 1;
    dino.visible = true;
    isJumping = false;
    isDucking = false;
    jumpVelocity = 0;
    obstacleTimer = 0;

    startGame();
  }

  function endGame() {
    gameOver = true;
    createDeathParticles();
    dino.visible = false;

    if (score > highScore) {
      highScore = score;
      localStorage.setItem('dinoHighScore', highScore);
      if (highScoreEl) highScoreEl.textContent = highScore;
    }

    if (finalScoreEl) finalScoreEl.textContent = score;
    if (gameOverScreen) gameOverScreen.style.display = 'flex';
  }

  function checkCollision(obstacle) {
    const dinoBox = {
      x: dino.position.x - 0.4,
      y: dino.position.y,
      width: 1.2,
      height: isDucking ? 1 : 2
    };

    const obsData = obstacle.userData;
    const obsBox = {
      x: obstacle.position.x - obsData.width / 2,
      y: obstacle.position.y,
      width: obsData.width,
      height: obsData.height
    };

    return !(dinoBox.x + dinoBox.width < obsBox.x ||
             dinoBox.x > obsBox.x + obsBox.width ||
             dinoBox.y + dinoBox.height < obsBox.y ||
             dinoBox.y > obsBox.y + obsBox.height);
  }

  function onResize() {
    const width = canvas.clientWidth;
    const height = canvas.clientHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
  }

  function onThemeChange() {
    colors = getThemeColors();

    // Update dino colors
    dino.traverse(child => {
      if (child.isMesh && child.material.emissive) {
        child.material.color.setHex(colors.dino);
        child.material.emissive.setHex(colors.dino);
      }
    });

    // Update ground
    ground.material.color.setHex(colors.ground);

    // Update obstacles
    obstacles.forEach(obs => {
      obs.traverse(child => {
        if (child.isMesh && child.material.emissive) {
          const color = obs.userData.type === 'pterodactyl' ? colors.secondary : colors.obstacle;
          child.material.color.setHex(color);
          child.material.emissive.setHex(color);
        }
      });
    });
  }

  function animate(time) {
    requestAnimationFrame(animate);

    const delta = Math.min((time - lastTime) / 16.67, 2);
    lastTime = time;

    if (gameStarted && !gameOver) {
      // Increase speed over time
      speed = baseSpeed + score * 0.00005;

      // Spawn obstacles
      obstacleTimer += delta;
      const spawnInterval = Math.max(50, 100 - score * 0.03);
      if (obstacleTimer > spawnInterval) {
        createObstacle();
        obstacleTimer = 0;
      }

      // Update ground lines (scrolling effect)
      groundLines.forEach(line => {
        line.position.x -= speed * delta;
        if (line.position.x < -40) {
          line.position.x += 80;
        }
      });

      // Update obstacles
      for (let i = obstacles.length - 1; i >= 0; i--) {
        const obs = obstacles[i];
        obs.position.x -= speed * delta;

        // Animate pterodactyl wings
        if (obs.userData.type === 'pterodactyl') {
          obs.userData.wingPhase += 0.2 * delta;
          const wingAngle = Math.sin(obs.userData.wingPhase) * 0.5;
          if (obs.userData.wingL) obs.userData.wingL.rotation.x = wingAngle;
          if (obs.userData.wingR) obs.userData.wingR.rotation.x = -wingAngle;
        }

        // Check if passed obstacle (score points!)
        if (!obs.userData.passed && obs.position.x < dino.position.x - 1) {
          obs.userData.passed = true;
          score += 100;
          if (scoreEl) scoreEl.textContent = score;
          createScoreParticles();
        }

        // Check collision
        if (checkCollision(obs)) {
          endGame();
        }

        // Remove off-screen obstacles
        if (obs.position.x < -15) {
          scene.remove(obs);
          obstacles.splice(i, 1);
        }
      }

      // Dino jump physics
      if (isJumping) {
        dino.position.y += jumpVelocity * delta;
        jumpVelocity -= gravity * delta;

        if (dino.position.y <= dino.userData.baseY) {
          dino.position.y = dino.userData.baseY;
          isJumping = false;
          jumpVelocity = 0;
          createJumpParticles();
        }
      }

      // Animate dino legs
      if (!isJumping && dino.userData.legL && dino.userData.legR) {
        const legAngle = Math.sin(time * 0.02 * speed * 10) * 0.5;
        dino.userData.legL.rotation.x = legAngle;
        dino.userData.legR.rotation.x = -legAngle;
      }

      // Bob dino head slightly
      if (dino.userData.head) {
        dino.userData.head.rotation.z = Math.sin(time * 0.005) * 0.05;
      }
    }

    // Update particles
    for (let i = particles.length - 1; i >= 0; i--) {
      const p = particles[i];
      p.position.x += p.userData.velocity.x;
      p.position.y += p.userData.velocity.y;
      p.position.z += p.userData.velocity.z;
      p.userData.velocity.y -= 0.008;
      p.userData.life -= 0.02;
      p.material.opacity = p.userData.life;

      if (p.userData.rotSpeed) {
        p.rotation.x += p.userData.rotSpeed.x;
        p.rotation.y += p.userData.rotSpeed.y;
        p.rotation.z += p.userData.rotSpeed.z;
      }

      if (p.userData.life <= 0) {
        scene.remove(p);
        particles.splice(i, 1);
      }
    }

    // Animate stars
    stars.forEach(star => {
      star.rotation.y += 0.0002;
    });

    // Camera shake on game over
    if (gameOver && particles.length > 0) {
      camera.position.x = (Math.random() - 0.5) * 0.2;
      camera.position.y = 2 + (Math.random() - 0.5) * 0.2;
    } else {
      camera.position.x += (0 - camera.position.x) * 0.1;
      camera.position.y += (2 - camera.position.y) * 0.1;
    }

    renderer.render(scene, camera);
  }

  // Load Three.js and initialize
  function loadThreeJS() {
    if (typeof THREE !== 'undefined') {
      init();
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
    script.onload = init;
    document.head.appendChild(script);
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadThreeJS);
  } else {
    loadThreeJS();
  }
})();
