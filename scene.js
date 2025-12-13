/**
 * 3D Cosmic Background Scene
 * Interactive particle system with floating geometric shapes
 * Supports dark/light theme switching
 */

(function() {
  'use strict';

  const canvas = document.getElementById('bg-canvas');
  if (!canvas) return;

  let scene, camera, renderer;
  let particles, particleMaterial;
  let shapes = [];
  let cores = [];
  let lines = [];
  let velocities;
  let basePositions;
  let colors;
  let time = 0;
  let isInitialized = false;

  const mouse = { x: 0, y: 0, targetX: 0, targetY: 0 };
  let mouse3D;
  const particleCount = 1500;
  const repulsionRadius = 8;
  const repulsionStrength = 0.15;

  // Theme color configurations
  function getThemeColors() {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    return {
      primary: isDark ? 0x3b82f6 : 0x2563eb,
      secondary: isDark ? 0x8b5cf6 : 0x7c3aed,
      particles: isDark ? 0x60a5fa : 0x3b82f6,
      particleOpacity: isDark ? 0.6 : 0.4,
      shapeOpacity: isDark ? 0.4 : 0.25,
      coreOpacity: isDark ? 0.7 : 0.5,
      lineOpacity: isDark ? 0.12 : 0.08
    };
  }

  function init() {
    if (isInitialized) return;
    isInitialized = true;

    mouse3D = new THREE.Vector3();
    colors = getThemeColors();

    // Scene setup
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      alpha: true,
      powerPreference: 'high-performance'
    });

    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setClearColor(0x000000, 0);

    createParticles();
    createShapes();
    createCores();
    createLines();

    camera.position.z = 25;

    // Event listeners
    window.addEventListener('mousemove', onMouseMove, { passive: true });
    window.addEventListener('touchmove', onTouchMove, { passive: true });
    window.addEventListener('resize', onResize, { passive: true });

    // Theme change observer
    const themeObserver = new MutationObserver(onThemeChange);
    themeObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme']
    });

    // Start animation
    animate();
  }

  function createParticles() {
    const geometry = new THREE.BufferGeometry();
    const positions = new Float32Array(particleCount * 3);
    basePositions = new Float32Array(particleCount * 3);
    velocities = new Float32Array(particleCount * 3);

    for (let i = 0; i < particleCount * 3; i += 3) {
      const x = (Math.random() - 0.5) * 60;
      const y = (Math.random() - 0.5) * 60;
      const z = (Math.random() - 0.5) * 40 - 10;
      positions[i] = x;
      positions[i + 1] = y;
      positions[i + 2] = z;
      basePositions[i] = x;
      basePositions[i + 1] = y;
      basePositions[i + 2] = z;
      velocities[i] = (Math.random() - 0.5) * 0.015;
      velocities[i + 1] = (Math.random() - 0.5) * 0.015;
      velocities[i + 2] = (Math.random() - 0.5) * 0.01;
    }

    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

    particleMaterial = new THREE.PointsMaterial({
      color: colors.particles,
      size: 0.06,
      transparent: true,
      opacity: colors.particleOpacity,
      blending: THREE.AdditiveBlending,
      sizeAttenuation: true
    });

    particles = new THREE.Points(geometry, particleMaterial);
    scene.add(particles);
  }

  function createShapes() {
    const geometries = [
      new THREE.IcosahedronGeometry(1, 0),
      new THREE.OctahedronGeometry(1, 0),
      new THREE.TetrahedronGeometry(1, 0),
      new THREE.DodecahedronGeometry(0.8, 0)
    ];

    for (let i = 0; i < 12; i++) {
      const geometry = geometries[Math.floor(Math.random() * geometries.length)];
      const material = new THREE.MeshBasicMaterial({
        color: Math.random() > 0.5 ? colors.primary : colors.secondary,
        wireframe: true,
        transparent: true,
        opacity: colors.shapeOpacity
      });

      const mesh = new THREE.Mesh(geometry, material);
      const posX = (Math.random() - 0.5) * 40;
      const posY = (Math.random() - 0.5) * 30;
      mesh.position.set(posX, posY, (Math.random() - 0.5) * 20 - 15);
      mesh.rotation.set(
        Math.random() * Math.PI,
        Math.random() * Math.PI,
        Math.random() * Math.PI
      );
      mesh.scale.setScalar(0.6 + Math.random() * 1.2);

      mesh.userData = {
        rotationSpeed: {
          x: (Math.random() - 0.5) * 0.015,
          y: (Math.random() - 0.5) * 0.015,
          z: (Math.random() - 0.5) * 0.015
        },
        floatSpeed: 0.3 + Math.random() * 0.7,
        floatOffset: Math.random() * Math.PI * 2,
        baseX: posX,
        baseY: posY
      };

      shapes.push(mesh);
      scene.add(mesh);
    }
  }

  function createCores() {
    const geometry = new THREE.SphereGeometry(0.12, 12, 12);

    for (let i = 0; i < 25; i++) {
      const material = new THREE.MeshBasicMaterial({
        color: Math.random() > 0.5 ? colors.primary : colors.secondary,
        transparent: true,
        opacity: colors.coreOpacity
      });

      const core = new THREE.Mesh(geometry, material);
      core.position.set(
        (Math.random() - 0.5) * 50,
        (Math.random() - 0.5) * 40,
        (Math.random() - 0.5) * 30 - 10
      );

      core.userData = {
        originalPos: core.position.clone(),
        speed: 0.2 + Math.random() * 0.5,
        offset: Math.random() * Math.PI * 2,
        orbitRadius: 1 + Math.random() * 2
      };

      cores.push(core);
      scene.add(core);
    }
  }

  function createLines() {
    for (let i = 0; i < 15; i++) {
      const material = new THREE.LineBasicMaterial({
        color: colors.primary,
        transparent: true,
        opacity: colors.lineOpacity
      });

      const geometry = new THREE.BufferGeometry();
      const positions = new Float32Array(6);
      geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

      const line = new THREE.Line(geometry, material);
      lines.push(line);
      scene.add(line);
    }
  }

  function onMouseMove(event) {
    mouse.targetX = (event.clientX / window.innerWidth) * 2 - 1;
    mouse.targetY = -(event.clientY / window.innerHeight) * 2 + 1;
  }

  function onTouchMove(event) {
    if (event.touches.length > 0) {
      mouse.targetX = (event.touches[0].clientX / window.innerWidth) * 2 - 1;
      mouse.targetY = -(event.touches[0].clientY / window.innerHeight) * 2 + 1;
    }
  }

  function onResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  }

  function onThemeChange() {
    colors = getThemeColors();

    // Update particles
    particleMaterial.color.setHex(colors.particles);
    particleMaterial.opacity = colors.particleOpacity;

    // Update shapes
    shapes.forEach(shape => {
      shape.material.color.setHex(Math.random() > 0.5 ? colors.primary : colors.secondary);
      shape.material.opacity = colors.shapeOpacity;
    });

    // Update cores
    cores.forEach(core => {
      core.material.color.setHex(Math.random() > 0.5 ? colors.primary : colors.secondary);
      core.material.opacity = colors.coreOpacity;
    });

    // Update lines
    lines.forEach(line => {
      line.material.color.setHex(colors.primary);
      line.material.opacity = colors.lineOpacity;
    });
  }

  function animate() {
    requestAnimationFrame(animate);

    time += 0.008;

    // Smooth mouse follow
    mouse.x += (mouse.targetX - mouse.x) * 0.08;
    mouse.y += (mouse.targetY - mouse.y) * 0.08;

    // Convert mouse to 3D world coordinates
    mouse3D.set(mouse.x * 25, mouse.y * 20, 0);

    // Update particles with repulsion
    const posArray = particles.geometry.attributes.position.array;
    for (let i = 0; i < particleCount * 3; i += 3) {
      // Base movement
      basePositions[i] += velocities[i];
      basePositions[i + 1] += velocities[i + 1];
      basePositions[i + 2] += velocities[i + 2];

      // Boundary check
      if (Math.abs(basePositions[i]) > 30) velocities[i] *= -1;
      if (Math.abs(basePositions[i + 1]) > 30) velocities[i + 1] *= -1;
      if (Math.abs(basePositions[i + 2]) > 20) velocities[i + 2] *= -1;

      // Calculate distance from mouse in 3D
      const dx = basePositions[i] - mouse3D.x;
      const dy = basePositions[i + 1] - mouse3D.y;
      const dz = basePositions[i + 2] - mouse3D.z;
      const dist = Math.sqrt(dx * dx + dy * dy + dz * dz);

      // Repulsion effect
      if (dist < repulsionRadius && dist > 0) {
        const force = (repulsionRadius - dist) / repulsionRadius;
        const repulse = force * force * repulsionStrength;
        posArray[i] = basePositions[i] + (dx / dist) * repulse * repulsionRadius;
        posArray[i + 1] = basePositions[i + 1] + (dy / dist) * repulse * repulsionRadius;
        posArray[i + 2] = basePositions[i + 2] + (dz / dist) * repulse * repulsionRadius;
      } else {
        // Smoothly return to base position
        posArray[i] += (basePositions[i] - posArray[i]) * 0.1;
        posArray[i + 1] += (basePositions[i + 1] - posArray[i + 1]) * 0.1;
        posArray[i + 2] += (basePositions[i + 2] - posArray[i + 2]) * 0.1;
      }
    }
    particles.geometry.attributes.position.needsUpdate = true;

    // Subtle rotation based on mouse
    particles.rotation.x += (mouse.y * 0.02 - particles.rotation.x) * 0.01;
    particles.rotation.y += (mouse.x * 0.02 - particles.rotation.y) * 0.01;

    // Update shapes with mouse repulsion
    shapes.forEach(shape => {
      shape.rotation.x += shape.userData.rotationSpeed.x;
      shape.rotation.y += shape.userData.rotationSpeed.y;
      shape.rotation.z += shape.userData.rotationSpeed.z;

      // Floating animation
      const floatY = Math.sin(time * shape.userData.floatSpeed + shape.userData.floatOffset) * 1.5;

      // Calculate repulsion from mouse
      const sdx = shape.userData.baseX - mouse3D.x;
      const sdy = shape.userData.baseY - mouse3D.y;
      const sdist = Math.sqrt(sdx * sdx + sdy * sdy);

      if (sdist < 12 && sdist > 0) {
        const sforce = (12 - sdist) / 12;
        shape.position.x = shape.userData.baseX + (sdx / sdist) * sforce * 3;
        shape.position.y = shape.userData.baseY + floatY + (sdy / sdist) * sforce * 3;
      } else {
        shape.position.x += (shape.userData.baseX - shape.position.x) * 0.05;
        shape.position.y += (shape.userData.baseY + floatY - shape.position.y) * 0.05;
      }
    });

    // Update cores with pulsing and orbital motion
    cores.forEach((core, i) => {
      const pulse = Math.sin(time * core.userData.speed * 2 + core.userData.offset);
      core.scale.setScalar(0.7 + pulse * 0.3);

      // Orbital motion
      const orbit = core.userData.orbitRadius;
      core.position.x = core.userData.originalPos.x + Math.sin(time * 0.3 + i * 0.5) * orbit;
      core.position.y = core.userData.originalPos.y + Math.cos(time * 0.2 + i * 0.5) * orbit;
    });

    // Update connection lines between nearby cores
    lines.forEach((line, i) => {
      if (i < cores.length - 1) {
        const positions = line.geometry.attributes.position.array;
        positions[0] = cores[i].position.x;
        positions[1] = cores[i].position.y;
        positions[2] = cores[i].position.z;
        positions[3] = cores[(i + 1) % cores.length].position.x;
        positions[4] = cores[(i + 1) % cores.length].position.y;
        positions[5] = cores[(i + 1) % cores.length].position.z;
        line.geometry.attributes.position.needsUpdate = true;

        // Dynamic opacity based on distance
        const dist = cores[i].position.distanceTo(cores[(i + 1) % cores.length].position);
        line.material.opacity = Math.max(0, colors.lineOpacity * (1 - dist * 0.05));
      }
    });

    // Camera follows mouse smoothly
    camera.position.x += (mouse.x * 5 - camera.position.x) * 0.03;
    camera.position.y += (mouse.y * 4 - camera.position.y) * 0.03;
    camera.lookAt(mouse.x * 2, mouse.y * 1.5, -5);

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

  // Start loading immediately
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadThreeJS);
  } else {
    loadThreeJS();
  }
})();
