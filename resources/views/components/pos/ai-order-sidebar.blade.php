{{-- resources/views/components/pos/ai-order-sidebar.blade.php --}}
<div x-data="aiOrderSidebar()" x-init="init()" class="font-sans">
    
    {{-- Floating Toggle Button di Sisi Kiri Bawah --}}
    <button type="button" 
            @click="isOpen = !isOpen"
            class="fixed bottom-4 left-4 z-50 bg-emerald-600 hover:bg-emerald-700 text-white rounded-full shadow-lg px-4 py-2.5 font-bold flex items-center space-x-2 border-2 border-white transition-all transform hover:scale-105 active:scale-95 focus:outline-none">
        <div class="relative flex items-center justify-center">
            <i class="fab fa-whatsapp text-lg"></i>
            <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 border border-white rounded-full animate-pulse" x-show="orders.length > 0"></span>
        </div>
        <span class="text-xs tracking-wide">Pesanan WA</span>
        <span class="px-2 py-0.5 text-xs font-extrabold bg-white text-emerald-700 rounded-full shadow-xs" x-text="orders.length">0</span>
    </button>

    {{-- Kontainer Pop-Up Utama di Kiri Bawah (Persis di Atas Tombol Toggle) --}}
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-20 left-4 z-50 w-[420px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col font-sans"
         style="max-height: calc(100vh - 120px); display: none;">
        
        {{-- Header Panel --}}
        <div class="px-4 py-3 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 text-white flex items-center justify-between shadow-sm">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center text-white shadow-inner">
                    <i class="fab fa-whatsapp text-lg"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <h3 class="font-bold text-sm tracking-wide text-white">Antrean WhatsApp</h3>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500 text-white"
                              x-text="orders.length">0</span>
                    </div>
                    <div class="flex items-center space-x-1.5 text-[11px] text-emerald-100/90">
                        <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
                        <span>Live Auto-sync (10s)</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-1">
                {{-- Refresh Button --}}
                <button type="button" 
                        @click="fetchOrders(true)"
                        title="Segarkan Antrean"
                        class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white transition active:scale-95 focus:outline-none">
                    <i class="fas fa-sync-alt text-xs" :class="{ 'animate-spin': loading }"></i>
                </button>
                {{-- Close / Minimize Button --}}
                <button type="button" 
                        @click="isOpen = false"
                        title="Tutup Antrean"
                        class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white transition active:scale-95 focus:outline-none">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </div>

        {{-- Subheader / Petunjuk Drag --}}
        <div class="px-4 py-2 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between text-xs text-emerald-800">
            <span class="flex items-center space-x-1.5 font-medium">
                <i class="fas fa-hand-pointer text-emerald-600"></i>
                <span>Tarik (Drag) card ke keranjang di kanan</span>
            </span>
            <span class="text-[11px] text-emerald-600" x-show="lastChecked" x-text="'Update: ' + lastChecked"></span>
        </div>

        {{-- Filter / Search Input (Jika Antrean Banyak) --}}
        <div class="px-3 pt-2.5 pb-1" x-show="orders.length > 3">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fas fa-search text-xs"></i>
                </span>
                <input type="text" 
                       x-model="searchTerm"
                       placeholder="Cari nama pelanggan / ukuran..."
                       class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition">
            </div>
        </div>

        {{-- Body: Daftar Card Pesanan --}}
        <div class="flex-1 p-3 overflow-y-auto space-y-2.5 min-h-[200px] max-h-[380px] scrollbar-thin scrollbar-thumb-slate-200">
            
            {{-- State: Loading Awal --}}
            <template x-if="loading && orders.length === 0">
                <div class="flex flex-col items-center justify-center py-10 text-slate-400 space-y-2">
                    <i class="fas fa-circle-notch animate-spin text-2xl text-emerald-500"></i>
                    <span class="text-xs">Memuat antrean WhatsApp...</span>
                </div>
            </template>

            {{-- State: Antrean Kosong --}}
            <template x-if="!loading && filteredOrders.length === 0">
                <div class="flex flex-col items-center justify-center py-10 px-4 text-center border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                        <i class="fab fa-whatsapp text-2xl text-slate-400"></i>
                    </div>
                    <h4 class="text-xs font-semibold text-slate-700 mb-0.5">Tidak Ada Pesanan Pending</h4>
                    <p class="text-[11px] text-slate-500 max-w-[200px]">
                        Pesanan WhatsApp yang dikonfirmasi 'OKE' oleh pelanggan akan otomatis muncul di sini.
                    </p>
                </div>
            </template>

            {{-- State: List Order Cards (Draggable HTML5) --}}
            <template x-for="order in filteredOrders" :key="order.id">
                <div :draggable="!inCartOrderIds.includes(order.id)"
                     @dragstart="handleDragStart($event, order)"
                     @dragend="handleDragEnd($event)"
                     :data-order-id="order.id"
                     :data-customer-id="order.customer_id"
                     :data-jumlah="order.jumlah_ball"
                     :data-ukuran="order.ukuran_produk"
                     class="group relative rounded-xl p-3.5 shadow-sm transition-all duration-200 select-none"
                     :class="{
                         'bg-emerald-50/80 border-2 border-emerald-400 ring-2 ring-emerald-200/60': inCartOrderIds.includes(order.id),
                         'bg-amber-50/70 border-2 border-amber-400 ring-2 ring-amber-200/60 shadow-md hover:border-amber-500 cursor-grab active:cursor-grabbing': !inCartOrderIds.includes(order.id) && isFutureDelivery(order),
                         'bg-white border border-slate-200 hover:shadow-md hover:border-emerald-400 cursor-grab active:cursor-grabbing': !inCartOrderIds.includes(order.id) && !isFutureDelivery(order),
                         'opacity-50 ring-2 ring-emerald-500 ring-offset-1': draggedOrderId === order.id
                     }">
                    
                    {{-- Grip Indicator (jika belum di-drag) --}}
                    <div x-show="!inCartOrderIds.includes(order.id)" 
                         class="absolute left-1.5 top-1/2 -translate-y-1/2 flex flex-col space-y-0.5 text-slate-300 transition-colors"
                         :class="isFutureDelivery(order) ? 'group-hover:text-amber-500' : 'group-hover:text-emerald-500'">
                        <i class="fas fa-ellipsis-v text-xs"></i>
                    </div>
                    {{-- Checkmark Icon (jika sudah di keranjang) --}}
                    <div x-show="inCartOrderIds.includes(order.id)" class="absolute left-1.5 top-1/2 -translate-y-1/2 text-emerald-600">
                        <i class="fas fa-check-circle text-xs"></i>
                    </div>

                    <div class="pl-3.5">
                        {{-- Row 1: Nama Pelanggan (Font Besar & Bold) & Nomor HP (Menonjol) --}}
                        <div class="flex items-start justify-between mb-2 gap-2">
                            <div class="min-w-0 flex-1">
                                {{-- Nama Pelanggan (text-lg font-bold) --}}
                                <div class="flex items-center space-x-1.5">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0"
                                          :class="inCartOrderIds.includes(order.id) ? 'bg-emerald-200 text-emerald-800' : (isFutureDelivery(order) ? 'bg-amber-200 text-amber-900' : 'bg-emerald-100 text-emerald-700')">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <h4 class="font-bold text-base sm:text-lg tracking-tight leading-snug truncate"
                                        :class="inCartOrderIds.includes(order.id) ? 'text-emerald-950 font-extrabold' : 'text-slate-900'"
                                        x-text="order.customer_name"></h4>
                                </div>

                                {{-- Nomor HP Pelanggan (Font Jelas & Menonjol) --}}
                                <div class="flex items-center space-x-1.5 text-xs sm:text-sm font-semibold text-slate-600 mt-0.5 pl-7">
                                    <i class="fab fa-whatsapp text-emerald-600 text-xs"></i>
                                    <span x-text="order.sender_phone"></span>
                                </div>
                            </div>

                            {{-- Waktu Masuk & Aksi --}}
                            <div class="flex items-center space-x-1 flex-shrink-0 pt-0.5">
                                <span class="text-[10px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded"
                                      x-text="order.time_formatted || order.time_ago"
                                      title="Waktu pesanan masuk"></span>
                                {{-- Tombol Buka Kunci --}}
                                <button type="button" 
                                        x-show="inCartOrderIds.includes(order.id)"
                                        @click.stop="unlockOrder(order.id, $event)"
                                        class="inline-flex items-center space-x-1 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 bg-white hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300 rounded border border-slate-300 transition shadow-2xs active:scale-95 cursor-pointer"
                                        title="Buka kunci agar pesanan ini bisa di-drag ulang">
                                    <i class="fas fa-undo text-[9px]"></i>
                                    <span>Batal</span>
                                </button>
                                {{-- Tombol Selesai --}}
                                <button type="button" 
                                        @click.stop="markDone(order.id, $event)"
                                        class="inline-flex items-center space-x-1 px-2 py-0.5 text-[10px] font-bold rounded border transition shadow-xs active:scale-95 cursor-pointer"
                                        :class="inCartOrderIds.includes(order.id) ? 'bg-emerald-600 text-white border-emerald-700 hover:bg-emerald-700 ring-2 ring-emerald-300' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white border-emerald-300 hover:border-emerald-600'"
                                        title="Tandai pesanan selesai & hilangkan dari antrean">
                                    <i class="fas fa-check text-[9px]"></i>
                                    <span>Selesai</span>
                                </button>
                            </div>
                        </div>

                        {{-- Peringatan Jadwal Kirim Belum Tiba (Jika delivery_time > waktu sekarang) --}}
                        <template x-if="isFutureDelivery(order)">
                            <div class="mb-2 px-2.5 py-1.5 bg-amber-100/90 border border-amber-300 rounded-lg flex items-center justify-between text-amber-900 text-xs font-bold shadow-2xs">
                                <span class="flex items-center space-x-1.5">
                                    <span class="text-sm">⚠️</span>
                                    <span>Jadwal Kirim Belum Tiba</span>
                                </span>
                                <span class="text-[11px] font-extrabold text-amber-900 bg-amber-200/90 px-1.5 py-0.5 rounded border border-amber-300"
                                      x-text="'Kirim: ' + getDeliveryTimeBadge(order)"></span>
                            </div>
                        </template>

                        {{-- Row 2: Varian Ukuran, Badge Waktu Kirim & Jumlah Ball --}}
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100">
                            {{-- Badge Ukuran & Delivery Time --}}
                            <div class="flex items-center space-x-1.5 flex-wrap gap-1">
                                {{-- Badge Ukuran Es --}}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold tracking-tight shadow-xs"
                                      :class="{
                                          'bg-emerald-100 text-emerald-800 border border-emerald-200': order.ukuran_produk === '20kg',
                                          'bg-sky-100 text-sky-800 border border-sky-200': order.ukuran_produk === '15kg',
                                          'bg-amber-100 text-amber-800 border border-amber-200': order.ukuran_produk === '10kg',
                                          'bg-slate-100 text-slate-800 border border-slate-200': !['20kg','15kg','10kg'].includes(order.ukuran_produk)
                                      }">
                                    <i class="fas fa-cube text-[9px] mr-1 opacity-70"></i>
                                    <span x-text="'Es ' + order.ukuran_produk"></span>
                                </span>

                                {{-- Badge Delivery Time (Jika ada) --}}
                                <template x-if="order.delivery_time">
                                    <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-md text-[11px] font-bold shadow-xs"
                                          :class="isFutureDelivery(order) ? 'bg-amber-500 text-white border border-amber-600' : 'bg-blue-100 text-blue-800 border border-blue-200'">
                                        <i class="fas fa-clock text-[9px]"></i>
                                        <span x-text="'Kirim: ' + getDeliveryTimeBadge(order)"></span>
                                    </span>
                                </template>

                                {{-- Badge Status Sudah di Keranjang --}}
                                <span x-show="inCartOrderIds.includes(order.id)" 
                                      class="inline-flex items-center space-x-1 px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-emerald-200 text-emerald-900 border border-emerald-400">
                                    <i class="fas fa-shopping-cart text-[9px]"></i>
                                    <span>Di Keranjang</span>
                                </span>
                            </div>

                            {{-- Jumlah Ball --}}
                            <div class="flex items-baseline space-x-1">
                                <span class="text-base font-extrabold text-emerald-700" x-text="order.jumlah_ball"></span>
                                <span class="text-xs font-bold text-slate-600">Ball</span>
                            </div>
                        </div>

                        {{-- Petunjuk Drag Hover vs Status Terkunci --}}
                        <div class="mt-2 text-center" x-show="inCartOrderIds.includes(order.id)">
                            <span class="inline-flex items-center space-x-1 text-[10px] font-bold text-emerald-800 bg-white/80 px-2.5 py-0.5 rounded-full border border-emerald-300 shadow-2xs">
                                <i class="fas fa-lock text-[9px] text-emerald-600"></i>
                                <span>Terkunci (Klik 'Selesai' jika transaksi checkout tuntas)</span>
                            </span>
                        </div>
                        <div class="mt-2 text-center opacity-0 group-hover:opacity-100 transition-opacity duration-150" x-show="!inCartOrderIds.includes(order.id)">
                            <span class="inline-flex items-center space-x-1 text-[10px] font-semibold px-2.5 py-0.5 rounded-full"
                                  :class="isFutureDelivery(order) ? 'text-amber-700 bg-amber-50' : 'text-emerald-600 bg-emerald-50'">
                                <i class="fas fa-arrows-alt text-[9px]"></i>
                                <span>Tarik ke Keranjang di Kanan</span>
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Footer Info --}}
        <div class="px-3.5 py-2 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex items-center justify-between">
            <span>Status: <strong class="text-emerald-600 font-semibold">Tervalidasi</strong></span>
            <span x-text="orders.length + ' Pesanan siap diproses'"></span>
        </div>
    </div>
</div>

<script>
    function aiOrderSidebar() {
        return {
            isOpen: false,
            orders: [],
            inCartOrderIds: [],
            loading: false,
            lastChecked: '',
            searchTerm: '',
            draggedOrderId: null,
            pollTimer: null,

            get filteredOrders() {
                if (!this.searchTerm.trim()) return this.orders;
                const q = this.searchTerm.toLowerCase();
                return this.orders.filter(o => 
                    (o.customer_name && o.customer_name.toLowerCase().includes(q)) ||
                    (o.ukuran_produk && o.ukuran_produk.toLowerCase().includes(q)) ||
                    (o.sender_phone && o.sender_phone.includes(q))
                );
            },

            // Helper pengecekan apakah waktu pengiriman di masa mendatang (belum waktunya dikirim)
            isFutureDelivery(order) {
                if (!order || !order.delivery_time) return false;
                try {
                    const dTime = new Date(order.delivery_time.replace(/-/g, '/'));
                    const now = new Date();
                    return dTime > now;
                } catch (e) {
                    return false;
                }
            },

            // Helper format jam pengiriman
            getDeliveryTimeBadge(order) {
                if (!order || !order.delivery_time) return null;
                if (order.delivery_time_formatted) return order.delivery_time_formatted;
                try {
                    const dTime = new Date(order.delivery_time.replace(/-/g, '/'));
                    return dTime.getHours().toString().padStart(2, '0') + ':' + 
                           dTime.getMinutes().toString().padStart(2, '0');
                } catch (e) {
                    return null;
                }
            },

            init() {
                this.fetchOrders();
                // Polling otomatis setiap 10 detik
                this.pollTimer = setInterval(() => {
                    this.fetchOrders();
                }, 10000);

                // Dengarkan event toggle dari tombol eksternal
                window.addEventListener('toggle-ai-orders', () => {
                    this.isOpen = !this.isOpen;
                });

                // Dengarkan event saat pesanan dimasukkan ke keranjang POS
                window.addEventListener('ai-order-in-cart', (e) => {
                    if (e.detail && e.detail.orderId) {
                        const id = parseInt(e.detail.orderId, 10);
                        if (!this.inCartOrderIds.includes(id)) {
                            this.inCartOrderIds.push(id);
                        }
                    }
                });

                // Dengarkan event jika pesanan di-unlock (dihapus/dibatalkan dari keranjang)
                window.addEventListener('ai-order-unlocked', (e) => {
                    if (e.detail && e.detail.orderId) {
                        const id = parseInt(e.detail.orderId, 10);
                        this.inCartOrderIds = this.inCartOrderIds.filter(i => i !== id);
                    }
                });

                // Dengarkan event jika pesanan selesai diproses oleh POS
                window.addEventListener('ai-order-completed', (e) => {
                    if (e.detail && e.detail.orderId) {
                        const id = parseInt(e.detail.orderId, 10);
                        this.orders = this.orders.filter(o => o.id !== id);
                        this.inCartOrderIds = this.inCartOrderIds.filter(i => i !== id);
                        window.dispatchEvent(new CustomEvent('ai-orders-count-changed', {
                            detail: { count: this.orders.length }
                        }));
                    }
                });
            },

            async fetchOrders(isManual = false) {
                if (isManual) this.loading = true;
                try {
                    const response = await fetch('/api/v1/kasir/ai-orders', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const json = await response.json();
                        this.orders = json.data || [];
                        const now = new Date();
                        this.lastChecked = now.getHours().toString().padStart(2, '0') + ':' + 
                                          now.getMinutes().toString().padStart(2, '0');
                        window.dispatchEvent(new CustomEvent('ai-orders-count-changed', {
                            detail: { count: this.orders.length }
                        }));
                    }
                } catch (err) {
                    console.warn('[AiOrderSidebar] Gagal mengambil antrean AI Orders:', err);
                } finally {
                    this.loading = false;
                }
            },

            handleDragStart(event, order) {
                // Hindari drag jika kasir menekan tombol Selesai atau pesanan sudah masuk keranjang
                if (event.target.closest('button') || this.inCartOrderIds.includes(order.id)) {
                    event.preventDefault();
                    return false;
                }

                this.draggedOrderId = order.id;
                window.currentDraggedAiOrder = order;
                
                // Set data transfer HTML5
                event.dataTransfer.effectAllowed = 'copyMove';
                const payload = JSON.stringify(order);
                event.dataTransfer.setData('application/json', payload);
                event.dataTransfer.setData('text/plain', payload);
                event.dataTransfer.setData('ai-order-id', order.id.toString());
                event.dataTransfer.setData('customer-id', (order.customer_id || '').toString());
                event.dataTransfer.setData('jumlah-ball', order.jumlah_ball.toString());
                event.dataTransfer.setData('ukuran-produk', order.ukuran_produk);

                // Dispatch event global agar komponen keranjang POS bisa mendengarkan
                window.dispatchEvent(new CustomEvent('ai-order-dragstart', {
                    detail: order
                }));
            },

            handleDragEnd(event) {
                this.draggedOrderId = null;
                window.currentDraggedAiOrder = null;
                window.dispatchEvent(new CustomEvent('ai-order-dragend'));
            },

            // Buka kunci pesanan agar kasir bisa drag ulang
            unlockOrder(orderId, event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                const numId = parseInt(orderId, 10);
                this.inCartOrderIds = this.inCartOrderIds.filter(i => i !== numId);
                window.dispatchEvent(new CustomEvent('ai-order-unlocked', {
                    detail: { orderId: numId }
                }));
            },

            // Kasir menandai pesanan selesai setelah checkout berhasil dilakukan di POS
            async markDone(orderId, event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                const numId = parseInt(orderId, 10);

                // 1. Reaktif hapus langsung dari state antrean lokal (Optimistic UI)
                const previousOrders = [...this.orders];
                const previousInCart = [...this.inCartOrderIds];
                this.orders = this.orders.filter(o => o.id !== numId);
                this.inCartOrderIds = this.inCartOrderIds.filter(i => i !== numId);

                if (window.aiOrdersInCart) {
                    window.aiOrdersInCart.delete(numId);
                }

                window.dispatchEvent(new CustomEvent('ai-orders-count-changed', {
                    detail: { count: this.orders.length }
                }));

                // 2. Panggil API ke backend untuk update status ke 'cancelled' / processed
                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                    const response = await fetch(`/api/v1/kasir/ai-orders/${numId}/processed`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        const err = await response.json().catch(() => ({}));
                        console.warn('[AiOrderSidebar] Gagal update status pesanan di server:', err);
                    }
                } catch (err) {
                    console.warn('[AiOrderSidebar] Gagal menghubungi server:', err);
                    // Rollback jika terjadi kegagalan jaringan
                    this.orders = previousOrders;
                    this.inCartOrderIds = previousInCart;
                    window.dispatchEvent(new CustomEvent('ai-orders-count-changed', {
                        detail: { count: this.orders.length }
                    }));
                }
            }
        };
    }
</script>
