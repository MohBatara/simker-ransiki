/**
 * Titik penanda untuk deretan kartu yang digeser (scroll-snap). `active` mengikuti kartu terdekat di tepi kiri
 * (kartu terakhir saat sudah di ujung); titik bisa diketuk untuk menggeser ke kartu itu. Elemen deretan:
 * x-ref="track" x-on:scroll.passive="update()".
 */
export default function scrollDots() {
    return {
        active: 0,

        offset(item) {
            return item.offsetLeft - parseFloat(getComputedStyle(this.$refs.track).scrollPaddingLeft || 0);
        },

        update() {
            const track = this.$refs.track;
            const items = [...track.children];

            if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 2) {
                this.active = items.length - 1;

                return;
            }

            let nearest = 0;
            items.forEach((item, i) => {
                if (Math.abs(this.offset(item) - track.scrollLeft) < Math.abs(this.offset(items[nearest]) - track.scrollLeft)) {
                    nearest = i;
                }
            });
            this.active = nearest;
        },

        go(index) {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.$refs.track.scrollTo({ left: this.offset(this.$refs.track.children[index]), behavior: reduce ? 'auto' : 'smooth' });
        },
    };
}
