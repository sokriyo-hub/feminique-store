import '@fontsource-variable/inter';
import '@fontsource/cormorant-garamond/300.css';
import '@fontsource/cormorant-garamond/400.css';
import '@fontsource/cormorant-garamond/500.css';
import '@fontsource/cormorant-garamond/400-italic.css';

import Alpine from 'alpinejs';
import './wishlist';
import './receipt';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

// ---- Category সারির inertia (mouse drag ছাড়ার পর কতটা গড়াবে) এখান থেকে বদলানো যায় ----
const GLIDE_FRICTION = 0.94; // ছোট = তাড়াতাড়ি থামে (০.৯২ কম গড়ায়, ০.৯৬ বেশি গড়ায়)
const GLIDE_MAX_SPEED = 3.5; // সর্বোচ্চ গতি (pixel প্রতি millisecond)

// ---- Hero slider: টানা (drag/swipe), নিচে দাগ-সূচক, নিজে নিজে বদলায় ----
Alpine.data('heroSlider', (count) => ({
    n: count,
    i: 0,
    dragging: false,
    moved: false,
    startX: 0,
    dx: 0,
    paused: false,
    elapsed: 0,
    interval: 5000, // কত মিলিসেকেন্ড পর পরের ছবি আসবে

    init() {
        this.$watch('i', () => {
            this.elapsed = 0;
        });

        if (this.n > 1 && !reduceMotion) {
            setInterval(() => {
                if (this.paused || this.dragging || document.hidden) return;
                this.elapsed += 100;
                if (this.elapsed >= this.interval) this.next();
            }, 100);
        }
    },

    get progress() {
        return reduceMotion ? 100 : Math.min(100, (this.elapsed / this.interval) * 100);
    },

    get trackStyle() {
        return 'transform: translate3d(calc(' + -this.i * 100 + '% + ' + this.dx + 'px), 0, 0)';
    },

    next() {
        this.i = (this.i + 1) % this.n;
    },

    go(index) {
        this.i = index;
    },

    down(event) {
        if (this.n < 2) return;
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        this.dragging = true;
        this.moved = false;
        this.startX = event.clientX;
        this.dx = 0;
    },

    move(event) {
        if (!this.dragging) return;
        let dx = event.clientX - this.startX;
        if (Math.abs(dx) > 6) this.moved = true;

        // প্রথম/শেষ ছবিতে আর টানা গেলে একটু শক্ত লাগবে
        if ((this.i === 0 && dx > 0) || (this.i === this.n - 1 && dx < 0)) dx *= 0.3;

        this.dx = dx;
    },

    up() {
        if (!this.dragging) return;
        const threshold = Math.min(120, this.$el.clientWidth * 0.15);

        if (this.dx < -threshold && this.i < this.n - 1) this.i++;
        else if (this.dx > threshold && this.i > 0) this.i--;

        this.dragging = false;
        this.dx = 0;
        this.elapsed = 0;
    },

    // টানার পর ভুল করে লিংক খুলে না যাওয়ার জন্য
    guard(event) {
        if (this.moved) {
            event.preventDefault();
            event.stopPropagation();
        }
    },
}));

// ---- Category সারি: টেনে/swipe করে ডানে-বামে স্ক্রল + endless loop + mouse inertia ----
// সারি screen-এর চেয়ে চওড়া হলে আগে-পরে একটা করে লুকানো copy বসে।
// স্ক্রল থামলে (বা টানার সময়) চুপচাপ মাঝের আসল অংশে ফিরিয়ে আনা হয়, তাই কখনো শেষ হয় না।
Alpine.data('dragScroll', () => ({
    dragging: false,
    moved: false,
    startX: 0,
    startLeft: 0,
    canLeft: false,
    canRight: true,
    thumbW: 100,
    looping: false,
    setStart: 0, // আসল অংশ যেখানে শুরু
    setW: 0, // আসল অংশের মোট প্রস্থ (gap সহ)
    timer: null,
    resizeTimer: null,
    lastW: 0,

    // inertia-র জন্য
    gliding: false,
    velocity: 0, // pixel প্রতি millisecond (ধনাত্মক = ডানে যাচ্ছে)
    lastX: 0,
    lastT: 0,
    frame: null,
    pos: 0,

    init() {
        this.$nextTick(() => {
            this.build();
            this.update();
        });

        window.addEventListener('resize', () => {
            // phone-এ address bar ওঠা-নামায় শুধু উচ্চতা বদলায়, তখন কিছু করার দরকার নেই
            if (this.$refs.track.clientWidth === this.lastW) return;
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(() => {
                this.build();
                this.update();
            }, 150);
        });
    },

    // দরকার হলে লুকানো copy বানিয়ে loop চালু করে
    build() {
        const track = this.$refs.track;
        this.stopGlide();
        this.lastW = track.clientWidth;

        track.querySelectorAll('[data-clone]').forEach((el) => el.remove());
        this.looping = false;

        const items = Array.from(track.querySelectorAll('[data-cat]'));
        if (!items.length) return;

        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        const first = items[0];
        const last = items[items.length - 1];
        const setW = last.offsetLeft + last.offsetWidth + gap - first.offsetLeft;

        // সব category screen-এ ধরে গেলে loop দরকার নেই
        if (setW - gap <= track.clientWidth + 4) {
            track.scrollLeft = 0;
            return;
        }

        const makeClone = (el) => {
            const copy = el.cloneNode(true);
            copy.removeAttribute('data-cat');
            copy.setAttribute('data-clone', '');
            copy.setAttribute('aria-hidden', 'true');
            copy.setAttribute('tabindex', '-1');
            return copy;
        };

        const before = document.createDocumentFragment();
        items.forEach((el) => before.appendChild(makeClone(el)));
        track.insertBefore(before, first);

        items.forEach((el) => track.appendChild(makeClone(el)));

        this.setW = setW;
        this.setStart = first.offsetLeft;
        this.looping = true;
        track.scrollLeft = this.setStart;
    },

    // আসল অংশের বাইরে চলে গেলে সমান দূরত্বে আসল অংশে ফিরিয়ে আনে (দেখতে একই)
    normalize() {
        if (!this.looping) return 0;
        const track = this.$refs.track;
        let shift = 0;

        if (track.scrollLeft < this.setStart) shift = this.setW;
        else if (track.scrollLeft >= this.setStart + this.setW) shift = -this.setW;

        if (shift) track.scrollLeft += shift;
        return shift;
    },

    update() {
        const track = this.$refs.track;

        if (this.looping) {
            this.canLeft = true;
            this.canRight = true;
            this.thumbW = 0;

            if (!this.dragging && !this.gliding) {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.normalize(), 120);
            }
            return;
        }

        const max = track.scrollWidth - track.clientWidth;

        this.canLeft = track.scrollLeft > 4;
        this.canRight = track.scrollLeft < max - 4;
        this.thumbW = track.scrollWidth > 0 ? Math.min(100, (track.clientWidth / track.scrollWidth) * 100) : 100;
    },

    by(direction) {
        const track = this.$refs.track;
        this.stopGlide();
        this.normalize();
        track.scrollBy({ left: direction * track.clientWidth * 0.8, behavior: 'smooth' });
    },

    // inertia থামানো (snap আবার চালু)
    stopGlide() {
        if (this.frame) cancelAnimationFrame(this.frame);
        this.frame = null;

        if (this.gliding) {
            this.gliding = false;
            this.$refs.track.style.scrollSnapType = '';
        }
    },

    // হাত ছাড়ার পর গতি ধীরে ধীরে কমিয়ে গড়িয়ে নেওয়া
    glide() {
        const track = this.$refs.track;
        this.gliding = true;
        track.style.scrollSnapType = 'none';
        this.pos = track.scrollLeft;
        let prev = performance.now();

        const step = (now) => {
            const dt = Math.min(now - prev, 34);
            prev = now;

            this.pos += this.velocity * dt;
            track.scrollLeft = this.pos;

            // loop না থাকলে দুই প্রান্তে গিয়ে থেমে যাবে
            if (!this.looping) {
                const max = track.scrollWidth - track.clientWidth;
                if (this.pos <= 0 || this.pos >= max) this.velocity = 0;
            }

            this.pos += this.normalize();
            this.velocity *= Math.pow(GLIDE_FRICTION, dt / 16.67);

            if (Math.abs(this.velocity) < 0.03) {
                this.stopGlide();
                this.update();
                return;
            }

            this.frame = requestAnimationFrame(step);
        };

        this.frame = requestAnimationFrame(step);
    },

    down(event) {
        if (event.button !== 0) return;

        // ঘুরন্ত অবস্থায় ক্লিক করে থামালে ভুল করে category খুলবে না
        this.moved = this.gliding;
        this.stopGlide();

        this.dragging = true;
        this.startX = event.pageX;
        this.startLeft = this.$refs.track.scrollLeft;
        this.lastX = event.pageX;
        this.lastT = performance.now();
        this.velocity = 0;
    },

    move(event) {
        if (!this.dragging) return;
        const dx = event.pageX - this.startX;
        if (Math.abs(dx) > 5) this.moved = true;

        this.$refs.track.scrollLeft = this.startLeft - dx;

        // টানার সময়ও সীমা পেরোলে ফিরিয়ে আনা, আর টানার হিসাব ঠিক রাখা
        const shift = this.normalize();
        if (shift) this.startLeft += shift;

        // গতি মাপা (একটু মসৃণ করে)
        const now = performance.now();
        const dt = now - this.lastT;
        if (dt > 0) {
            const v = (this.lastX - event.pageX) / dt;
            this.velocity = 0.6 * v + 0.4 * this.velocity;
            this.lastX = event.pageX;
            this.lastT = now;
        }
    },

    up() {
        if (!this.dragging) return;
        this.dragging = false;

        // ছাড়ার আগে mouse থেমে থাকলে গড়াবে না
        const idle = performance.now() - this.lastT;

        if (this.moved && idle < 80 && Math.abs(this.velocity) > 0.08) {
            this.velocity = Math.max(-GLIDE_MAX_SPEED, Math.min(GLIDE_MAX_SPEED, this.velocity));
            this.glide();
        } else {
            this.update();
        }
    },

    guard(event) {
        if (this.moved) {
            event.preventDefault();
            event.stopPropagation();
        }
    },
}));

// ---- Product ছবির gallery: hover-zoom, < > তীর, কিবোর্ড ও swipe ----
Alpine.data('productGallery', (count) => ({
    n: count,
    active: 0,
    zoom: false,
    ox: 50,
    oy: 50,
    touchX: 0,
    canZoom: canHover,

    next() {
        if (this.n < 2) return;
        this.zoom = false;
        this.active = (this.active + 1) % this.n;
    },

    prev() {
        if (this.n < 2) return;
        this.zoom = false;
        this.active = (this.active - 1 + this.n) % this.n;
    },

    go(index) {
        this.zoom = false;
        this.active = index;
    },

    onMove(event) {
        if (!this.canZoom || this.n < 1) return;

        // তীরের ওপর mouse থাকলে zoom হবে না
        if (event.target.closest('[data-nozoom]')) {
            this.zoom = false;
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        this.ox = Math.min(100, Math.max(0, ((event.clientX - rect.left) / rect.width) * 100));
        this.oy = Math.min(100, Math.max(0, ((event.clientY - rect.top) / rect.height) * 100));
        this.zoom = true;
    },

    touchStart(event) {
        this.touchX = event.changedTouches[0].clientX;
    },

    touchEnd(event) {
        const dx = event.changedTouches[0].clientX - this.touchX;
        if (Math.abs(dx) > 50) {
            dx < 0 ? this.next() : this.prev();
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();

// ---- Animation-এর তীব্রতা এখান থেকে কমানো-বাড়ানো যায় ----
const MAX_TILT = 4; // ডিগ্রি (০ দিলে tilt বন্ধ)

document.addEventListener('DOMContentLoaded', () => {
    // scroll করলে ধীরে ভেসে ওঠা
    const items = document.querySelectorAll('[data-reveal]');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );

        items.forEach((el) => observer.observe(el));
    }

    // কার্ডে cursor-অনুসারী glow ও হালকা tilt (শুধু desktop mouse)
    if (!reduceMotion && canHover) {
        document.querySelectorAll('[data-tilt]').forEach((el) => {
            el.addEventListener('mousemove', (event) => {
                const rect = el.getBoundingClientRect();
                const x = (event.clientX - rect.left) / rect.width;
                const y = (event.clientY - rect.top) / rect.height;

                el.style.setProperty('--ry', ((x - 0.5) * 2 * MAX_TILT).toFixed(2) + 'deg');
                el.style.setProperty('--rx', ((0.5 - y) * 2 * MAX_TILT).toFixed(2) + 'deg');
                el.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
                el.style.setProperty('--my', (y * 100).toFixed(1) + '%');
            });

            el.addEventListener('mouseleave', () => {
                el.style.setProperty('--rx', '0deg');
                el.style.setProperty('--ry', '0deg');
            });
        });
    }
});