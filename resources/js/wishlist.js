import Alpine from 'alpinejs';

const KEY = 'feminique_wishlist';
const MAX_ITEMS = 100;

function read() {
    try {
        const raw = JSON.parse(localStorage.getItem(KEY) || '[]');
        if (!Array.isArray(raw)) return [];

        return [...new Set(raw.map(Number).filter((n) => Number.isInteger(n) && n > 0))].slice(-MAX_ITEMS);
    } catch (e) {
        return [];
    }
}

function write(ids) {
    try {
        localStorage.setItem(KEY, JSON.stringify(ids));
    } catch (e) {
        // browser storage blocked: wishlist works only until the page closes
    }
}

// Wishlist store: saved product ids live in this browser only
Alpine.store('wishlist', {
    ids: read(),

    get count() {
        return this.ids.length;
    },

    has(id) {
        return this.ids.includes(Number(id));
    },

    toggle(id) {
        id = Number(id);
        this.ids = this.has(id) ? this.ids.filter((x) => x !== id) : [...this.ids, id].slice(-MAX_ITEMS);
        write(this.ids);
    },

    replace(ids) {
        this.ids = ids;
        write(this.ids);
    },
});

// Keep several open tabs in sync
window.addEventListener('storage', (event) => {
    if (event.key === KEY) Alpine.store('wishlist').ids = read();
});

// /wishlist page: fetch the saved products from the server and show them
Alpine.data('wishlistPage', (url) => ({
    html: '',
    loading: true,
    failed: false,

    async init() {
        await this.load();
        this.$watch('$store.wishlist.ids', () => this.sync());
    },

    async load() {
        const store = Alpine.store('wishlist');
        const ids = [...store.ids].reverse(); // newest first

        if (!ids.length) {
            this.html = '';
            this.loading = false;
            return;
        }

        this.loading = true;
        this.failed = false;

        try {
            const response = await fetch(url + '?ids=' + ids.join(','), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('bad response');

            this.html = await response.text();
            this.$nextTick(() => this.prune());
        } catch (e) {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    // Remove ids of products that no longer exist (deleted in admin)
    prune() {
        const store = Alpine.store('wishlist');
        const present = [...this.$refs.list.querySelectorAll('[data-wid]')].map((el) => Number(el.dataset.wid));
        const kept = store.ids.filter((id) => present.includes(id));

        if (kept.length !== store.ids.length) store.replace(kept);
    },

    // A heart was removed (or added in another tab)
    sync() {
        const ids = Alpine.store('wishlist').ids;
        const cards = [...this.$refs.list.querySelectorAll('[data-wid]')];

        cards.forEach((el) => {
            if (!ids.includes(Number(el.dataset.wid))) el.remove();
        });

        const shown = cards.map((el) => Number(el.dataset.wid));
        if (ids.some((id) => !shown.includes(id))) this.load();
    },
}));