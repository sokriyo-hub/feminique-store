import '@fontsource-variable/inter';
import '@fontsource/cormorant-garamond/300.css';
import '@fontsource/cormorant-garamond/400.css';
import '@fontsource/cormorant-garamond/500.css';
import '@fontsource/cormorant-garamond/400-italic.css';

import Alpine from 'alpinejs';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

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

// ---- ক্লিক-ধরে টেনে (drag) ডানে-বামে স্ক্রল করার তালিকা (category সারি) ----
Alpine.data('dragScroll', () => ({
    dragging: false,
    moved: false,
    startX: 0,
    startLeft: 0,
    canLeft: false,
    canRight: true,
    thumbW: 100,

    init() {
        this.$nextTick(() => this.update());
        window.addEventListener('resize', () => this.update());
    },

    update() {
        const track = this.$refs.track;
        const max = track.scrollWidth - track.clientWidth;

        this.canLeft = track.scrollLeft > 4;
        this.canRight = track.scrollLeft < max - 4;
        this.thumbW = track.scrollWidth > 0 ? Math.min(100, (track.clientWidth / track.scrollWidth) * 100) : 100;
    },

    by(direction) {
        const track = this.$refs.track;
        track.scrollBy({ left: direction * track.clientWidth * 0.8, behavior: 'smooth' });
    },

    down(event) {
        if (event.button !== 0) return;
        this.dragging = true;
        this.moved = false;
        this.startX = event.pageX;
        this.startLeft = this.$refs.track.scrollLeft;
    },

    move(event) {
        if (!this.dragging) return;
        const dx = event.pageX - this.startX;
        if (Math.abs(dx) > 5) this.moved = true;
        this.$refs.track.scrollLeft = this.startLeft - dx;
    },

    up() {
        this.dragging = false;
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