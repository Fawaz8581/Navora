<?php

namespace App\Http\Controllers;

use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackagingAssistantController extends Controller
{
    /**
     * Berikan rekomendasi teknis kemasan cerdas berdasarkan profil produk UMKM.
     */
    public function recommend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:100'],
            'volume' => ['required', 'string', 'in:small,medium,large'],
            'priority' => ['nullable', 'string', 'in:budget,quality,eco,shelf_life'],
            'preference' => ['nullable', 'string', 'max:500'],
        ]);

        $product = trim($validated['product']);
        $lower = mb_strtolower($product, 'UTF-8');
        $priority = $validated['priority'] ?? 'budget';
        $size = ! empty($validated['size']) ? trim($validated['size']) : null;
        $volume = $validated['volume'];
        $preference = ! empty($validated['preference']) ? trim($validated['preference']) : null;

        $analysis = $this->analyzeProductCategory($lower, $validated['category'] ?? null);
        $packagingRecommendations = $this->determinePackagingSpecs($analysis, $priority, $size, $volume);
        $costEstimation = $this->estimateProductionCost($analysis, $volume, $priority);
        $regulatoryGuidance = $this->getRegulatoryAndStorageAdvice($analysis);
        $matchedVendors = $this->matchRealVendors($analysis);

        return response()->json([
            'status' => 'success',
            'product' => $product,
            'category_detected' => $analysis['category_label'],
            'characteristics' => $analysis['characteristics'],
            'packaging_types' => $packagingRecommendations['types'],
            'material_specification' => $packagingRecommendations['material'],
            'barrier_protection' => $packagingRecommendations['barrier'],
            'cost_estimation' => $costEstimation,
            'volume_strategy' => $costEstimation['volume_strategy'],
            'regulatory_compliance' => $regulatoryGuidance,
            'vendor_recommendations' => $matchedVendors,
            'rfq_prefill' => [
                'suggested_type' => $packagingRecommendations['types'][0]['name'] ?? 'Standing Pouch',
                'suggested_material' => $packagingRecommendations['material']['name'] ?? 'Aluminium Foil / Kraft',
                'suggested_quantity' => $volume === 'small' ? 500 : ($volume === 'medium' ? 2500 : 10000),
                'notes' => "Rekomendasi AI Navora: {$packagingRecommendations['types'][0]['name']} berbahan {$packagingRecommendations['material']['name']} ({$packagingRecommendations['barrier']}). Estimasi volume {$size}.",
            ],
        ]);
    }

    /**
     * Menganalisis karakteristik fisik, kimia, dan keawetan produk.
     */
    private function analyzeProductCategory(string $lower, ?string $manualCategory): array
    {
        $isCoffee = (bool) preg_match('/kopi|coffee|espresso|arabika|robusta|biji kopi|ground coffee/i', $lower);
        $isLiquid = (bool) preg_match('/minuman|jus|sirup|teh cair|susu|madu|sambal|saus|kecap|cuka|sabun cair|shampoo|minyak|essence|serum/i', $lower);
        $isCrispyDry = (bool) preg_match('/kripik|keripik|snack|camilan|biskuit|kue kering|makaroni|basreng|abon|popcorn/i', $lower);
        $isPowder = (bool) preg_match('/bubuk|tepung|rempah|bumbu tabur|serbuk|matcha|protein/i', $lower);
        $isFrozen = (bool) preg_match('/frozen|beku|dimsum|nugget|sosis|bakso|daging|ikan/i', $lower);
        $isCosmetics = (bool) preg_match('/skincare|krim|lotion|lip|parfum|toner|kosmetik|sabun batang/i', $lower);
        $isFashionCraft = (bool) preg_match('/baju|kaos|hijab|tas|sepatu|kain|kerajinan|aksesoris|gelang|lilin/i', $lower);

        if ($isCoffee) {
            return [
                'category_label' => 'Kopi Sangrai / Bubuk (Specialty Coffee)',
                'characteristics' => 'Sensitif terhadap oksidasi oksigen, kelembapan udara, dan sinar UV langsung. Biji kopi mengeluarkan gas CO2 pasca-roasting.',
                'needs_degassing_valve' => true,
                'liquid_tight' => false,
                'light_barrier' => 'high',
                'default_material' => 'Alufoil 100-120 mikron atau Metalized Foil Food Grade',
            ];
        }

        if ($isFrozen) {
            return [
                'category_label' => 'Makanan Beku & Olahan (Frozen Food)',
                'characteristics' => 'Disimpan dalam suhu beku (-18°C), risiko freezer burn dan kebocoran saat proses thawing (pencairan).',
                'needs_degassing_valve' => false,
                'liquid_tight' => true,
                'light_barrier' => 'medium',
                'default_material' => 'Nylon/LLDPE Vacuum Bag (tahan tusukan & suhu beku)',
            ];
        }

        if ($isLiquid) {
            return [
                'category_label' => 'Cairan, Minuman & Saus Berlemak/Minyak',
                'characteristics' => 'Tekanan hidrostatis, risiko kebocoran seal sisi samping, dan potensi oksidasi minyak atau keasaman.',
                'needs_degassing_valve' => false,
                'liquid_tight' => true,
                'light_barrier' => 'medium',
                'default_material' => 'Botol PET/Kaca atau Standing Pouch Spout (PET/AL/SPE)',
            ];
        }

        if ($isCrispyDry) {
            return [
                'category_label' => 'Camilan Renyah & Makanan Kering (Crispy Snacks)',
                'characteristics' => 'Higroskopis (sangat mudah menyerap uap air hingga melempem) dan rentan terhadap ketengikan oksidasi minyak.',
                'needs_degassing_valve' => false,
                'liquid_tight' => false,
                'light_barrier' => 'high',
                'default_material' => 'Metalized Pet (VMPET) / Aluminium Foil laminasi Matte/Glossy',
            ];
        }

        if ($isPowder) {
            return [
                'category_label' => 'Bubuk Halus, Bumbu & Minuman Serbuk',
                'characteristics' => 'Rentan menggumpal bila terkena kelembapan udara; debu bubuk berisiko mengotori area heat seal.',
                'needs_degassing_valve' => false,
                'liquid_tight' => false,
                'light_barrier' => 'high',
                'default_material' => 'Alufoil Multi-layer atau Sachet Three-side Seal Foil',
            ];
        }

        if ($isCosmetics) {
            return [
                'category_label' => 'Produk Kecantikan & Perawatan Diri',
                'characteristics' => 'Membutuhkan nilai estetika tinggi, kedap udara untuk menjaga stabilitas zat aktif, dan tahan bahan kimia ringan.',
                'needs_degassing_valve' => false,
                'liquid_tight' => true,
                'light_barrier' => 'high',
                'default_material' => 'Botol Airless / Tube HDPE / Box Karton Ivory Premium',
            ];
        }

        if ($isFashionCraft) {
            return [
                'category_label' => 'Fesyen, Kerajinan & Barang Retail',
                'characteristics' => 'Fokus utama pada perlindungan debu saat pengiriman logistik, unboxing experience yang berkesan, dan identitas brand.',
                'needs_degassing_valve' => false,
                'liquid_tight' => false,
                'light_barrier' => 'low',
                'default_material' => 'Polymailer Biodegradable / Box Corrugated Die-cut / Zipper Klip Transparan',
            ];
        }

        return [
            'category_label' => $manualCategory ?: 'Produk Konsumsi Umum / Retail',
            'characteristics' => 'Membutuhkan perlindungan standar terhadap debu, kelembapan ruangan, dan penanganan selama logistik kurir.',
            'needs_degassing_valve' => false,
            'liquid_tight' => false,
            'light_barrier' => 'medium',
            'default_material' => 'Standing Pouch Kraft Window / Box Karton Duplex Foodgrade',
        ];
    }

    /**
     * Memberikan opsi tipe kemasan, bahan, ketebalan, dan perlindungan barier.
     */
    private function determinePackagingSpecs(array $analysis, string $priority, ?string $size, string $volume): array
    {
        $types = [];
        $cat = $analysis['category_label'];

        if (str_contains($cat, 'Kopi')) {
            $types[] = [
                'name' => 'Standing Pouch Zipper + One-way Valve',
                'suitable_for' => 'Biji kopi dan bubuk (100g, 250g, 500g, 1kg)',
                'thickness' => '110–130 mikron',
                'reason' => 'Katup degassing membuang gas CO2 tanpa membiarkan udara luar masuk, menjaga aroma crema kopi hingga 6-12 bulan.',
            ];
            $types[] = [
                'name' => 'Flat Bottom Pouch (Box Pouch)',
                'suitable_for' => 'Specialty coffee premium dengan nilai jual tinggi',
                'thickness' => '120–140 mikron',
                'reason' => 'Bentuk tegak kokoh dan estetik di rak display display kopi cafe.',
            ];
            $types[] = [
                'name' => 'Drip Bag Coffee Sachet',
                'suitable_for' => 'Kopi siap seduh per serving (single serve 10-15g)',
                'thickness' => 'Sachet luar foil 80 mikron + filter non-woven Jepang',
                'reason' => 'Praktis untuk segmen traveler atau pekerja kantor.',
            ];
        } elseif (str_contains($cat, 'Cairan')) {
            $types[] = [
                'name' => 'Standing Pouch dengan Spout Cap (Corong Tutup Ulir)',
                'suitable_for' => 'Saus, sambal kemasan isi ulang, sabun cair, madu',
                'thickness' => '120–150 mikron (Multi-layer Nylon + LLDPE)',
                'reason' => 'Lebih hemat ongkir dan volume hingga 70% dibanding botol kaca, serta anti-pecah.',
            ];
            $types[] = [
                'name' => 'Botol PET Food Grade (Tutup Segel Lock)',
                'suitable_for' => 'Jus buah dingin, minuman herbal, kopi susu kekinian',
                'thickness' => 'Bobot preform 18–28 gram',
                'reason' => 'Jernih, transparan menampilkan warna alami produk, dan mudah dipasangi label stiker.',
            ];
        } elseif (str_contains($cat, 'Makanan Beku')) {
            $types[] = [
                'name' => 'Vacuum Bag Nylon Polyamide Tahan Suhu Beku',
                'suitable_for' => 'Dimsum, sosis, daging potong, nugget artisan',
                'thickness' => '75–100 mikron',
                'reason' => 'Mencegah kristalisasi es (freezer burn) dan menjaga tekstur rasa daging tidak alot saat digoreng.',
            ];
            $types[] = [
                'name' => 'Standing Pouch Khusus Suhu Minus Dingin',
                'suitable_for' => 'Camilan beku kemasan display supermarket',
                'thickness' => '90–110 mikron',
                'reason' => 'Tampilan menarik di dalam freezer rak kaca minimarket.',
            ];
        } elseif (str_contains($cat, 'Camilan')) {
            $types[] = [
                'name' => 'Standing Pouch Full Foil dengan Zipper Lock',
                'suitable_for' => 'Keripik gurih berlemak, makaroni renyah, kue kering',
                'thickness' => '90–110 mikron',
                'reason' => 'Perlindungan maksimal dari sinar matahari yang memicu bau tengik, menjaga kerenyahan hingga 8-12 bulan.',
            ];
            $types[] = [
                'name' => 'Center Seal Pillow Pouch (Kemasan Bantal)',
                'suitable_for' => 'Snack porsi ekonomis (warung & ritel)',
                'thickness' => '60–75 mikron',
                'reason' => 'Biaya per unit paling murah untuk kapasitas produksi ribuan pcs.',
            ];
        } elseif (str_contains($cat, 'Fesyen')) {
            $types[] = [
                'name' => 'Polymailer Plastik Daur Ulang / Biodegradable',
                'suitable_for' => 'Kaos distro, kemeja, hijab, tas kanvas',
                'thickness' => '50–60 mikron dengan double adhesive tamper-evident seal',
                'reason' => 'Tahan air hujan saat pengiriman ekspedisi, perekat sekali buka untuk keamanan isi.',
            ];
            $types[] = [
                'name' => 'Kotak Kardus Corrugated Mailer Box (Die-cut E-Flute)',
                'suitable_for' => 'Sepatu, paket gift box, bundling produk fesyen',
                'thickness' => 'Ketebalan 1.5–2.0 mm (Kokoh)',
                'reason' => 'Memberikan pengalaman unboxing premium yang mengundang ulasan positif pelanggan.',
            ];
        } else {
            $types[] = [
                'name' => 'Standing Pouch Kraft Paper dengan Jendela Bening (Window)',
                'suitable_for' => 'Aneka produk kering yang ingin menampilkan isi di dalam',
                'thickness' => '130 mikron (Kraft + PET + PE)',
                'reason' => 'Kesan ramah lingkungan (eco-friendly) sekaligus konsumen dapat melihat fisik produk.',
            ];
            $types[] = [
                'name' => 'Box Karton Duplex / Ivory Cetak Full Color',
                'suitable_for' => 'Kemasan sekunder luar botol atau sachet',
                'thickness' => 'Gramatur 250–350 gsm',
                'reason' => 'Memberikan perlindungan fisik benturan dan ruang informasi legalitas produk yang luas.',
            ];
        }

        $materialDesc = match ($priority) {
            'eco' => [
                'name' => 'Eco Kraft Paper Bersertifikasi FSC + Bio-based PE Inner',
                'advantages' => 'Kesan alami, mudah terurai, dan ramah lingkungan.',
                'ideal_for' => 'Target konsumen sadar lingkungan (eco-conscious).',
            ],
            'quality' => [
                'name' => 'Multi-layer Pure Aluminium Foil (PET / AL / LLDPE)',
                'advantages' => 'Kerapatan barier tertinggi di dunia kemasan (zero light & gas transmission).',
                'ideal_for' => 'Produk ekspor, specialty brand, dan masa simpan maksimal > 1 tahun.',
            ],
            'shelf_life' => [
                'name' => 'Metalized Barrier Film (PET / VMPET / CPP)',
                'advantages' => 'Tahan kelembapan tinggi, menjaga aroma dan kerenyahan optimal.',
                'ideal_for' => 'Distribusi ritel modern dan supermarket luar kota.',
            ],
            default => [
                'name' => 'Standard Food Grade Flexible Film (OPP / VMPET / PE)',
                'advantages' => 'Keseimbangan terbaik antara biaya terjangkau dan keamanan produk.',
                'ideal_for' => 'UMKM skala berkembang yang mengejar efisiensi modal produksi.',
            ],
        };

        return [
            'types' => $types,
            'material' => $materialDesc,
            'barrier' => $analysis['characteristics'],
        ];
    }

    /**
     * Menghitung estimasi biaya produksi per pcs & strategi volume MOQ.
     */
    private function estimateProductionCost(array $analysis, string $volume, string $priority): array
    {
        $basePrice = 1200;

        if (str_contains($analysis['category_label'], 'Kopi') || str_contains($analysis['category_label'], 'Cairan')) {
            $basePrice = 1800;
        } elseif (str_contains($analysis['category_label'], 'Fesyen')) {
            $basePrice = 900;
        }

        if ($priority === 'quality') {
            $basePrice *= 1.35;
        } elseif ($priority === 'eco') {
            $basePrice *= 1.25;
        } elseif ($priority === 'budget') {
            $basePrice *= 0.90;
        }

        $smallRange = 'Rp '.number_format($basePrice * 1.5, 0, ',', '.').' – Rp '.number_format($basePrice * 2.1, 0, ',', '.');
        $mediumRange = 'Rp '.number_format($basePrice * 1.0, 0, ',', '.').' – Rp '.number_format($basePrice * 1.4, 0, ',', '.');
        $largeRange = 'Rp '.number_format($basePrice * 0.65, 0, ',', '.').' – Rp '.number_format($basePrice * 0.85, 0, ',', '.');

        $currentEstimate = match ($volume) {
            'small' => $smallRange,
            'medium' => $mediumRange,
            'large' => $largeRange,
        };

        $strategy = match ($volume) {
            'small' => 'Gunakan metode sablon manual 1–2 warna pada kantong polos (ready-stock) atau manfaatkan fitur "Patungan MOQ Navora" untuk membagi beban kuota cetak pabrik bersama UMKM lain.',
            'medium' => 'Anda memenuhi syarat untuk Digital Printing kemasan fleksibel dengan full color tanpa biaya pelat silinder silinder film (hemat jutaan rupiah modal awal).',
            'large' => 'Gunakan metode Rotogravure Industri. Harga satuan akan turun drastis (hingga di bawah Rp 1.000/pcs) dengan konsistensi warna kelas industri internasional.',
        };

        return [
            'estimated_price_range' => $currentEstimate,
            'tier_small' => $smallRange.' / pcs (Volume < 1.000 pcs)',
            'tier_medium' => $mediumRange.' / pcs (Volume 1.000–5.000 pcs)',
            'tier_large' => $largeRange.' / pcs (Volume > 5.000 pcs)',
            'volume_strategy' => $strategy,
            'recommended_printing' => $volume === 'small' ? 'Sablon Manual / Label Stiker Vinyl' : ($volume === 'medium' ? 'Digital Printing Full Color (No Cylinder Fee)' : 'Rotogravure Industri'),
        ];
    }

    /**
     * Panduan regulasi label BPOM/Halal dan anjuran penyimpanan.
     */
    private function getRegulatoryAndStorageAdvice(array $analysis): array
    {
        return [
            'label_checklist' => [
                'Nama brand dagang dan nama jenis produk baku.',
                'Daftar komposisi bahan (diurutkan dari yang terbanyak).',
                'Nomor perizinan edar resmi: P-IRT (skala rumahan dinas kesehatan) atau BPOM RI MD/ML.',
                'Sertifikasi Halal resmi BPJPH Kementerian Agama.',
                'Informasi berat bersih / isi bersih (Netto).',
                'Nama dan alamat jelas produsen / distributor.',
                'Tanggal kedaluwarsa (Expired Date) & Kode Produksi (Batch Code).',
                'Tabel Informasi Nilai Gizi (Nutrition Facts) jika wajib.',
            ],
            'storage_tips' => 'Simpan stok kemasan kosong di tempat sejuk dan kering, terhindar dari paparan matahari langsung atau uap minyak agar lem perekat dan zipper tidak berkurang kekuatannya.',
        ];
    }

    /**
     * Mencari vendor nyata dari database yang relevan dengan kebutuhan.
     */
    private function matchRealVendors(array $analysis): array
    {
        $categoryKey = $analysis['category_label'];

        $listings = VendorListing::query()
            ->with(['vendor.businessProfile'])
            ->where('status', 'active')
            ->latest('id')
            ->limit(4)
            ->get();

        if ($listings->isEmpty()) {
            return [];
        }

        return $listings->map(function (VendorListing $listing) {
            $profile = $listing->vendor?->businessProfile;

            return [
                'id' => $listing->id,
                'vendor_user_id' => $listing->vendor_user_id,
                'name' => $listing->name,
                'vendor_name' => $profile?->business_name ?: 'Vendor Navora Terverifikasi',
                'city' => $profile?->location ?: 'Indonesia',
                'moq' => $listing->minimum_order_quantity,
                'price_per_unit' => $listing->price_per_unit,
                'pricing_type' => $listing->pricing_type,
                'material' => $listing->material ?: 'Kustom Spesifikasi',
                'is_verified' => $profile?->verification_status === 'approved',
                'is_pro' => (bool) $listing->vendor?->vendorSubscriptions()->where('payment_status', 'paid')->exists(),
            ];
        })->toArray();
    }
}
