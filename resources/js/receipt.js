import Alpine from 'alpinejs';
import { toPng } from 'html-to-image';

// Order receipt: download as an image, or print / save as PDF
Alpine.data('receipt', (code) => ({
    busy: false,
    failed: false,

    async download() {
        this.busy = true;
        this.failed = false;

        try {
            const card = this.$refs.card;

            const dataUrl = await toPng(card, {
                pixelRatio: 2,
                cacheBust: true,
                backgroundColor: getComputedStyle(card).backgroundColor,
                width: card.scrollWidth,
                height: card.scrollHeight + 8,
            });

            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = 'heshelmart-order-' + code + '.png';
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch (e) {
            this.failed = true;
        } finally {
            this.busy = false;
        }
    },

    print() {
        window.print();
    },
}));