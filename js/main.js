/* ==========================================
   ILDEN KI Consulting — Premium JavaScript
   GSAP ScrollTrigger + Lenis smooth scroll +
   cinematic text reveals + magnetic buttons +
   multi-step form
   ========================================== */

document.addEventListener('DOMContentLoaded', () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ===== LENIS SMOOTH SCROLL =====
    if (!prefersReducedMotion && window.Lenis) {
        const lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            smooth: true,
        });
        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);

        // Connect Lenis to GSAP ScrollTrigger
        if (window.gsap && window.ScrollTrigger) {
            lenis.on('scroll', ScrollTrigger.update);
            gsap.ticker.add((time) => lenis.raf(time * 1000));
            gsap.ticker.lagSmoothing(0);
        }

        // Smooth scroll for anchor links via Lenis
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const href = anchor.getAttribute('href');
                if (href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) lenis.scrollTo(target, { offset: -80 });
            });
        });
    } else {
        // Fallback smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const href = anchor.getAttribute('href');
                if (href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const top = target.getBoundingClientRect().top + window.scrollY - 80;
                    window.scrollTo({ top, behavior: 'smooth' });
                }
            });
        });
    }

    // ===== NAVBAR =====
    const navbar = document.getElementById('navbar');
    const stickyCta = document.getElementById('stickyCta');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
            // Sticky CTA: show after scrolling past hero
            if (stickyCta) {
                stickyCta.classList.toggle('visible', window.scrollY > window.innerHeight);
            }
        }, { passive: true });
    }

    // ===== MOBILE NAV =====
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    const navOverlay = document.getElementById('navOverlay');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
            if (navOverlay) navOverlay.classList.toggle('active');
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
        });

        navMenu.querySelectorAll('a:not(.dropdown-trigger)').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
                if (navOverlay) navOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        });

        if (navOverlay) {
            navOverlay.addEventListener('click', () => {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
                navOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        document.querySelectorAll('.dropdown-trigger').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    trigger.closest('.nav-dropdown').classList.toggle('open');
                }
            });
        });
    }

    // ===== GSAP ANIMATIONS =====
    if (!prefersReducedMotion && window.gsap && window.ScrollTrigger) {
        gsap.registerPlugin(ScrollTrigger);

        // --- Hero entrance timeline ---
        const heroOverline = document.querySelector('.hero-overline');
        const heroH1 = document.querySelector('.hero h1');
        const heroText = document.querySelector('.hero-text');
        const heroCta = document.querySelector('.hero-cta');
        const heroVisual = document.querySelector('.hero-visual');

        // Mark hero elements so they don't get double-animated
        const heroEls = [heroOverline, heroH1, heroText, heroCta, heroVisual].filter(Boolean);
        heroEls.forEach(el => el.setAttribute('data-hero', ''));

        if (heroH1) {
            const heroTl = gsap.timeline({ defaults: { ease: 'power4.out' } });

            // Split h1 into lines for staggered reveal
            const h1Text = heroH1.innerHTML;
            const lines = h1Text.split('<br>').length > 1 ? h1Text.split('<br>') : [h1Text];
            heroH1.innerHTML = lines.map(line => `<span class="line-wrap"><span class="line-inner">${line.trim()}</span></span>`).join('');

            const style = document.createElement('style');
            style.textContent = `.line-wrap { display: block; overflow: hidden; } .line-inner { display: block; }`;
            document.head.appendChild(style);

            heroTl
                .fromTo(heroOverline, { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6 }, 0.2)
                .fromTo('.line-inner', { y: '110%' }, { y: '0%', duration: 0.9, stagger: 0.15 }, 0.4)
                .fromTo(heroText, { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6 }, 0.9)
                .fromTo(heroCta, { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5 }, 1.1);

            if (heroVisual) {
                heroTl.fromTo(heroVisual, { x: 60, opacity: 0 }, { x: 0, opacity: 1, duration: 1, ease: 'power3.out' }, 0.5);
            }
        }

        // --- Scroll-triggered section reveals (skip hero elements) ---
        document.querySelectorAll('[data-animate]').forEach(el => {
            if (el.hasAttribute('data-hero') || el.closest('[data-hero]')) return;

            const type = el.dataset.animate || 'up';
            const from = { opacity: 0 };
            const to = { opacity: 1, duration: 0.8, ease: 'power3.out' };

            if (type === 'up' || type === '') { from.y = 50; to.y = 0; }
            else if (type === 'fade') { /* just opacity */ }
            else if (type === 'slide-left') { from.x = -60; to.x = 0; }
            else if (type === 'slide-right') { from.x = 60; to.x = 0; }
            else if (type === 'scale') { from.scale = 0.92; to.scale = 1; }

            gsap.fromTo(el, from, {
                ...to,
                scrollTrigger: {
                    trigger: el,
                    start: 'top 88%',
                    once: true,
                },
            });
        });

        // --- Staggered children ---
        document.querySelectorAll('[data-stagger]').forEach(container => {
            if (container.hasAttribute('data-hero') || container.closest('[data-hero]')) return;

            gsap.fromTo(container.children,
                { y: 40, opacity: 0 },
                {
                    y: 0, opacity: 1,
                    duration: 0.6,
                    stagger: 0.1,
                    ease: 'power3.out',
                    scrollTrigger: {
                        trigger: container,
                        start: 'top 85%',
                        once: true,
                    },
                }
            );
        });

        // --- Parallax on hero visual ---
        if (heroVisual) {
            gsap.to(heroVisual, {
                y: 80,
                ease: 'none',
                scrollTrigger: {
                    trigger: '.hero',
                    start: 'top top',
                    end: 'bottom top',
                    scrub: true,
                },
            });
        }

        // --- Counter animation via GSAP ---
        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseFloat(el.dataset.count);
            const suffix = el.dataset.suffix || '';
            const prefix = el.dataset.prefix || '';
            const obj = { val: 0 };

            gsap.to(obj, {
                val: target,
                duration: 2,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: el,
                    start: 'top 80%',
                    once: true,
                },
                onUpdate: () => {
                    el.textContent = prefix + Math.round(obj.val) + suffix;
                },
            });
        });

        // --- Section heading reveals ---
        document.querySelectorAll('.section-header h2, .about-text h2, .contact-info h2').forEach(h2 => {
            gsap.from(h2, {
                y: 40,
                opacity: 0,
                duration: 0.8,
                ease: 'power3.out',
                scrollTrigger: {
                    trigger: h2,
                    start: 'top 88%',
                    once: true,
                },
            });
        });

        // --- Bento cards 3D tilt on hover ---
        if (window.innerWidth > 768) {
            document.querySelectorAll('.bento-card').forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = (e.clientX - rect.left) / rect.width;
                    const y = (e.clientY - rect.top) / rect.height;
                    gsap.to(card, {
                        rotateX: (y - 0.5) * -6,
                        rotateY: (x - 0.5) * 6,
                        duration: 0.4,
                        ease: 'power2.out',
                        transformPerspective: 800,
                    });
                });
                card.addEventListener('mouseleave', () => {
                    gsap.to(card, { rotateX: 0, rotateY: 0, duration: 0.6, ease: 'power2.out' });
                });
            });
        }

    } else if (!prefersReducedMotion) {
        // --- FALLBACK: CSS-based scroll animations (no GSAP) ---
        const animatedEls = document.querySelectorAll('[data-animate]');
        // Add will-animate class so CSS hides them
        animatedEls.forEach(el => el.classList.add('will-animate'));

        const animObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    animObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
        animatedEls.forEach(el => animObserver.observe(el));

        // Stagger children (CSS fallback)
        document.querySelectorAll('[data-stagger]').forEach(container => {
            Array.from(container.children).forEach((child, i) => {
                child.style.setProperty('--i', i);
            });
        });

        // Counter animation fallback
        const counters = document.querySelectorAll('[data-count]');
        if (counters.length) {
            const countObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        const target = parseFloat(el.dataset.count);
                        const suffix = el.dataset.suffix || '';
                        const prefix = el.dataset.prefix || '';
                        const duration = 2000;
                        const start = performance.now();
                        function update(now) {
                            const p = Math.min((now - start) / duration, 1);
                            const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
                            el.textContent = prefix + Math.round(target * eased) + suffix;
                            if (p < 1) requestAnimationFrame(update);
                        }
                        requestAnimationFrame(update);
                        countObserver.unobserve(el);
                    }
                });
            }, { threshold: 0.5 });
            counters.forEach(c => countObserver.observe(c));
        }
    }

    // ===== MAGNETIC BUTTONS =====
    if (!prefersReducedMotion && window.innerWidth > 768) {
        document.querySelectorAll('.btn-primary, .btn-blue').forEach(btn => {
            btn.addEventListener('mousemove', (e) => {
                const rect = btn.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                if (window.gsap) {
                    gsap.to(btn, { x: x * 0.15, y: y * 0.15, duration: 0.3, ease: 'power2.out' });
                } else {
                    btn.style.transform = `translate(${x * 0.12}px, ${y * 0.12}px)`;
                }
            });
            btn.addEventListener('mouseleave', () => {
                if (window.gsap) {
                    gsap.to(btn, { x: 0, y: 0, duration: 0.5, ease: 'elastic.out(1, 0.5)' });
                } else {
                    btn.style.transform = '';
                }
            });
        });
    }

    // ===== CASES HORIZONTAL SCROLL (drag) =====
    document.querySelectorAll('.cases-scroll').forEach(track => {
        let isDown = false, startX, scrollLeft;
        track.addEventListener('mousedown', (e) => { isDown = true; startX = e.pageX - track.offsetLeft; scrollLeft = track.scrollLeft; });
        track.addEventListener('mouseleave', () => { isDown = false; });
        track.addEventListener('mouseup', () => { isDown = false; });
        track.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - track.offsetLeft;
            track.scrollLeft = scrollLeft - (x - startX) * 1.5;
        });
    });

    // ===== MULTI-STEP FORM =====
    const form = document.getElementById('kontaktForm');
    const formContainer = document.getElementById('multistepForm') || document.querySelector('.form-panel');
    if (form && formContainer) {
        const steps = form.querySelectorAll('.form-step');
        const progressSteps = formContainer.querySelectorAll('.progress-step');
        const progressFill = formContainer.querySelector('.progress-fill');
        const formSuccess = formContainer.querySelector('.form-success');
        let currentStep = 1;
        const totalSteps = steps.length || 3;

        function goToStep(step) {
            if (step > currentStep) {
                const cur = form.querySelector(`.form-step[data-step="${currentStep}"]`);
                if (cur) {
                    const required = cur.querySelectorAll('[required]');
                    let valid = true;
                    required.forEach(field => {
                        if (field.type === 'radio') {
                            const group = cur.querySelectorAll(`input[name="${field.name}"]`);
                            if (!Array.from(group).some(r => r.checked)) {
                                valid = false;
                                cur.querySelectorAll('.option-card-inner').forEach(c => {
                                    c.style.borderColor = 'rgba(239,68,68,0.4)';
                                    setTimeout(() => { c.style.borderColor = ''; }, 2000);
                                });
                            }
                        } else if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = 'rgba(239,68,68,0.4)';
                            setTimeout(() => { field.style.borderColor = ''; }, 2000);
                        }
                    });
                    if (!valid) return;
                }
            }

            currentStep = step;
            steps.forEach(s => s.classList.remove('active'));
            const target = form.querySelector(`.form-step[data-step="${step}"]`);
            if (target) target.classList.add('active');
            if (progressFill) progressFill.style.width = `${(step / totalSteps) * 100}%`;
            if (progressSteps) {
                progressSteps.forEach(ps => {
                    const n = parseInt(ps.dataset.step);
                    ps.classList.remove('active', 'completed');
                    if (n === step) ps.classList.add('active');
                    else if (n < step) ps.classList.add('completed');
                });
            }
        }

        form.querySelectorAll('.btn-next, [data-next]').forEach(btn => {
            btn.addEventListener('click', () => goToStep(parseInt(btn.dataset.next)));
        });
        form.querySelectorAll('.btn-prev, [data-prev]').forEach(btn => {
            btn.addEventListener('click', () => goToStep(parseInt(btn.dataset.prev)));
        });

        if (progressSteps) {
            progressSteps.forEach(ps => {
                ps.style.cursor = 'pointer';
                ps.addEventListener('click', () => {
                    const t = parseInt(ps.dataset.step);
                    if (t < currentStep) goToStep(t);
                });
            });
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            steps.forEach(s => s.classList.remove('active'));
            const progress = formContainer.querySelector('.form-progress');
            if (progress) progress.style.display = 'none';
            if (formSuccess) formSuccess.classList.add('active');
            console.log('Form submitted:', Object.fromEntries(new FormData(form)));
        });
    }
});
