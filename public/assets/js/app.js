/**
 * TiredProd - Main JavaScript
 */

(function() {
    'use strict';

    // ===== Gate System =====
    const gateOverlay = document.getElementById('gate-overlay');
    const gateForm = document.getElementById('gate-form');
    const gateInput = document.getElementById('gate-input');

    if (gateForm) {
        gateForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = gateInput.value;
            
            try {
                const response = await fetch('/api/gate/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password })
                });
                
                if (response.ok) {
                    gateOverlay.style.animation = 'fadeOut 0.5s ease-out forwards';
                    setTimeout(() => gateOverlay.remove(), 500);
                } else {
                    gateInput.classList.add('error');
                    gateInput.value = '';
                    setTimeout(() => gateInput.classList.remove('error'), 500);
                }
            } catch (err) {
                console.error('Gate error:', err);
            }
        });
    }

    // ===== Navigation Menu =====
    const navToggle = document.getElementById('nav-toggle');
    const menuOverlay = document.getElementById('menu-overlay');

    if (navToggle && menuOverlay) {
        navToggle.addEventListener('click', () => {
            navToggle.classList.toggle('active');
            menuOverlay.classList.toggle('active');
            document.body.style.overflow = menuOverlay.classList.contains('active') ? 'hidden' : '';
        });

        // Expandable menu items
        document.querySelectorAll('.menu-expandable').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.expand;
                const submenu = document.getElementById(`submenu-${target}`);
                
                btn.classList.toggle('expanded');
                submenu.classList.toggle('expanded');
            });
        });

        // Close menu on link click
        menuOverlay.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navToggle.classList.remove('active');
                menuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }

    // ===== Logout =====
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            try {
                await fetch('/api/auth/logout', { method: 'POST' });
                window.location.href = '/';
            } catch (err) {
                console.error('Logout error:', err);
            }
        });
    }

    // ===== Auth Form =====
    const authForm = document.getElementById('auth-form');
    if (authForm) {
        const stepEmail = document.getElementById('step-email');
        const stepOTP = document.getElementById('step-otp');
        const emailInput = document.getElementById('email');
        const otpInput = document.getElementById('otp');
        const usernameGroup = document.getElementById('username-group');
        const usernameInput = document.getElementById('username');
        const rememberInput = document.getElementById('remember');
        
        let currentEmail = '';
        let isNewUser = false;

        authForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!stepEmail.classList.contains('hidden')) {
                // Step 1: Request OTP
                currentEmail = emailInput.value;
                
                try {
                    const response = await fetch('/api/auth/request-otp', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: currentEmail })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        isNewUser = data.isNewUser;
                        stepEmail.classList.add('hidden');
                        stepOTP.classList.remove('hidden');
                        
                        if (isNewUser) {
                            usernameGroup.classList.remove('hidden');
                        }
                        
                        otpInput.focus();
                        
                        // Debug: show OTP in console (remove in production)
                        if (data.debug_otp) {
                            console.log('DEBUG OTP:', data.debug_otp);
                        }
                    } else {
                        alert(data.error || 'Failed to send OTP');
                    }
                } catch (err) {
                    console.error('OTP request error:', err);
                    alert('Failed to send OTP. Please try again.');
                }
            } else {
                // Step 2: Verify OTP
                const otp = otpInput.value.toUpperCase();
                const username = usernameInput.value;
                const remember = rememberInput.checked;
                
                try {
                    const response = await fetch('/api/auth/verify-otp', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            email: currentEmail, 
                            otp, 
                            username: isNewUser ? username : null,
                            remember 
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.href = data.redirect || '/';
                    } else {
                        alert(data.error || 'Invalid OTP');
                        otpInput.value = '';
                        otpInput.focus();
                    }
                } catch (err) {
                    console.error('OTP verify error:', err);
                    alert('Verification failed. Please try again.');
                }
            }
        });
    }

    // ===== Google Sign In =====
    const googleBtn = document.getElementById('google-signin');
    if (googleBtn) {
        googleBtn.addEventListener('click', () => {
            // TODO: Implement Google OAuth flow
            alert('Google Sign In coming soon!');
        });
    }

    // ===== Slideshow =====
    const slideshow = document.getElementById('slideshow');
    if (slideshow) {
        // Demo images (replace with actual pinned images from API)
        const demoImages = [
            'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1200',
            'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1200',
            'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=1200'
        ];
        
        const track = slideshow.querySelector('.slideshow-track');
        const dotsContainer = document.getElementById('slideshow-dots');
        const prevBtn = slideshow.querySelector('.slideshow-prev');
        const nextBtn = slideshow.querySelector('.slideshow-next');
        
        let currentSlide = 0;
        let autoplayInterval;
        
        // Create slides
        demoImages.forEach((src, index) => {
            const slide = document.createElement('div');
            slide.className = `slideshow-slide ${index === 0 ? 'active' : ''}`;
            slide.innerHTML = `<img src="${src}" alt="Portfolio image ${index + 1}" loading="lazy">`;
            track.appendChild(slide);
            
            const dot = document.createElement('button');
            dot.className = `slideshow-dot ${index === 0 ? 'active' : ''}`;
            dot.addEventListener('click', () => goToSlide(index));
            dotsContainer.appendChild(dot);
        });
        
        const slides = track.querySelectorAll('.slideshow-slide');
        const dots = dotsContainer.querySelectorAll('.slideshow-dot');
        
        function goToSlide(index) {
            slides[currentSlide].classList.remove('active');
            dots[currentSlide].classList.remove('active');
            currentSlide = (index + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.add('active');
        }
        
        function nextSlide() {
            goToSlide(currentSlide + 1);
        }
        
        function prevSlide() {
            goToSlide(currentSlide - 1);
        }
        
        function startAutoplay() {
            autoplayInterval = setInterval(nextSlide, 6000);
        }
        
        function stopAutoplay() {
            clearInterval(autoplayInterval);
        }
        
        prevBtn.addEventListener('click', () => {
            stopAutoplay();
            prevSlide();
            startAutoplay();
        });
        
        nextBtn.addEventListener('click', () => {
            stopAutoplay();
            nextSlide();
            startAutoplay();
        });
        
        slideshow.addEventListener('mouseenter', stopAutoplay);
        slideshow.addEventListener('mouseleave', startAutoplay);
        
        startAutoplay();
    }

    // ===== Dashboard Navigation =====
    const dashNavItems = document.querySelectorAll('.dash-nav-item');
    if (dashNavItems.length > 0) {
        dashNavItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const section = item.dataset.section;
                
                // Update nav
                dashNavItems.forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
                
                // Update sections
                document.querySelectorAll('.dash-section').forEach(sec => {
                    sec.classList.remove('active');
                });
                
                const targetSection = document.getElementById(`section-${section}`);
                if (targetSection) {
                    targetSection.classList.add('active');
                }
            });
        });
        
        // Animate stat counters
        const statValues = document.querySelectorAll('.stat-value[data-count]');
        statValues.forEach(el => {
            const target = parseInt(el.dataset.count) || 0;
            animateCounter(el, target);
        });
    }
    
    function animateCounter(element, target) {
        const duration = 1200;
        const start = 0;
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (target - start) * eased);
            
            if (element.textContent.startsWith('$')) {
                element.textContent = '$' + current.toLocaleString();
            } else {
                element.textContent = current.toLocaleString();
            }
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }

    // ===== Responsive Device Detection =====
    function detectDevice() {
        const width = window.innerWidth;
        const isMobile = width < 768;
        const isTablet = width >= 768 && width < 1024;
        const isDesktop = width >= 1024;
        
        document.body.classList.toggle('is-mobile', isMobile);
        document.body.classList.toggle('is-tablet', isTablet);
        document.body.classList.toggle('is-desktop', isDesktop);
        
        return { isMobile, isTablet, isDesktop };
    }
    
    detectDevice();
    window.addEventListener('resize', detectDevice);

    // ===== Add fadeOut animation =====
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(style);

})();
