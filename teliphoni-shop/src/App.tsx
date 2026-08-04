import { useState, useMemo } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Slider } from "@/components/ui/slider";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import {
  ShoppingCart,
  Search,
  Grid3X3,
  List,
  Smartphone,
  X,
  Camera,
  Globe,
  Phone,
  ChevronLeft,
  ChevronRight,
  Sun,
  Moon,
} from "lucide-react";

const ALL_PRODUCTS = [
  { id: 1, name: "Samsung Galaxy Tab A11+ 5G (8/256)", category: "Tablettes", brand: "Samsung", price: 4174.80, condition: "Neuf", ram: "8 Go", storage: "256 Go", connectivity: "5G" },
  { id: 2, name: "Redmi Pad 2 Pro 5G (8/256)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 4534.80, condition: "Neuf", ram: "8 Go", storage: "256 Go", connectivity: "5G" },
  { id: 3, name: "Redmi Pad 2 4G (4/128)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 2458.80, condition: "Neuf", ram: "4 Go", storage: "128 Go", connectivity: "4G" },
  { id: 4, name: "Samsung Galaxy Tab A11+ 5G (6/128)", category: "Tablettes", brand: "Samsung", price: 3538.80, condition: "Neuf", ram: "6 Go", storage: "128 Go", connectivity: "5G" },
  { id: 5, name: "Redmi Pad 2 4G (4/64)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 2254.80, condition: "Neuf", ram: "4 Go", storage: "64 Go", connectivity: "4G" },
  { id: 6, name: "Galaxy Tab A9 (4/64) — 1 an garantie ✅", category: "Tablettes", brand: "Samsung", price: 1654.80, condition: "Neuf", ram: "4 Go", storage: "64 Go", connectivity: "4G" },
  { id: 7, name: "Redmi Pad 2 Pro 5G (6/128)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 3838.80, condition: "Neuf", ram: "6 Go", storage: "128 Go", connectivity: "5G" },
  { id: 8, name: "Redmi Pad Pro (6/128)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 3574.80, condition: "Neuf", ram: "6 Go", storage: "128 Go", connectivity: "4G" },
  { id: 9, name: "Xiaomi Redmi Pad SE (4/128)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 1654.80, condition: "Neuf", ram: "4 Go", storage: "128 Go", connectivity: "4G" },
  { id: 10, name: "Xiaomi Redmi Pad SE (4/64)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 1414.80, condition: "Neuf", ram: "4 Go", storage: "64 Go", connectivity: "4G" },
  { id: 11, name: "HP EliteBook Ultra G1q 14 Snapdragon X Elite (16/512)", category: "Laptops", brand: "HP", price: 10738.80, condition: "Comme Neuf", ram: "16 Go", storage: "512 Go", connectivity: "WiFi" },
  { id: 12, name: "HP OMEN 16 I9/14eme (32/1TB) RTX 5060", category: "Laptops", brand: "HP", price: 22618.80, condition: "Comme Neuf", ram: "32 Go", storage: "1 To", connectivity: "WiFi" },
  { id: 13, name: "HP Pavilion 15 i7/13eme (16/512)", category: "Laptops", brand: "HP", price: 8490.00, condition: "Neuf", ram: "16 Go", storage: "512 Go", connectivity: "WiFi" },
  { id: 14, name: "HP Envy x360 (16/1TB)", category: "Laptops", brand: "HP", price: 12990.00, condition: "Comme Neuf", ram: "16 Go", storage: "1 To", connectivity: "WiFi" },
  { id: 15, name: "Samsung Galaxy Tab S9 FE (6/128)", category: "Tablettes", brand: "Samsung", price: 3290.00, condition: "Neuf", ram: "6 Go", storage: "128 Go", connectivity: "WiFi" },
  { id: 16, name: "HP ProBook 450 G10 (8/256)", category: "Laptops", brand: "HP", price: 6990.00, condition: "Comme Neuf", ram: "8 Go", storage: "256 Go", connectivity: "WiFi" },
  { id: 17, name: "Redmi Pad SE 4G (6/128)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 1890.00, condition: "Neuf", ram: "6 Go", storage: "128 Go", connectivity: "4G" },
  { id: 18, name: "HP Laptop 15s i5/12eme (8/512)", category: "Laptops", brand: "HP", price: 5490.00, condition: "Neuf", ram: "8 Go", storage: "512 Go", connectivity: "WiFi" },
  { id: 19, name: "Samsung Galaxy Tab A8 (4/64)", category: "Tablettes", brand: "Samsung", price: 1290.00, condition: "Neuf", ram: "4 Go", storage: "64 Go", connectivity: "WiFi" },
  { id: 20, name: "HP EliteBook 840 G9 (16/512)", category: "Laptops", brand: "HP", price: 7890.00, condition: "Reconditionné", ram: "16 Go", storage: "512 Go", connectivity: "WiFi" },
  { id: 21, name: "Redmi Pad Pro 5G (8/256)", category: "Tablettes", brand: "Xiaomi/Redmi", price: 4190.00, condition: "Neuf", ram: "8 Go", storage: "256 Go", connectivity: "5G" },
];

const BRAND_GRADIENTS: Record<string, string> = {
  Samsung: "from-blue-500 to-blue-700",
  "Xiaomi/Redmi": "from-orange-400 to-red-500",
  HP: "from-indigo-500 to-blue-800",
};

const CONDITION_STYLES: Record<string, { bg: string; text: string }> = {
  "Neuf": { bg: "bg-emerald-100 text-emerald-700", text: "Neuf" },
  "Comme Neuf": { bg: "bg-sky-100 text-sky-700", text: "Comme Neuf" },
  "Reconditionné": { bg: "bg-amber-100 text-amber-700", text: "Reconditionné" },
};

const PAGE_SIZE = 12;

function formatPrice(price: number) {
  return price.toLocaleString("fr-MA", { minimumFractionDigits: 2 }) + " MAD";
}

function getInitials(name: string) {
  return name.split(" ").slice(0, 2).map((w) => w[0]).join("").toUpperCase();
}

type Product = typeof ALL_PRODUCTS[0];

export default function App() {
  const [darkMode, setDarkMode] = useState(false);
  const [cartCount, setCartCount] = useState(0);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedCategory, setSelectedCategory] = useState("Toutes");
  const [priceRange, setPriceRange] = useState([0, 25000]);
  const [selectedConditions, setSelectedConditions] = useState<string[]>([]);
  const [selectedBrands, setSelectedBrands] = useState<string[]>([]);
  const [sortOrder, setSortOrder] = useState("default");
  const [viewMode, setViewMode] = useState<"grid" | "list">("grid");
  const [currentPage, setCurrentPage] = useState(1);

  const filtered = useMemo(() => {
    let list = [...ALL_PRODUCTS];
    if (selectedCategory !== "Toutes") list = list.filter((p) => p.category === selectedCategory);
    list = list.filter((p) => p.price >= priceRange[0] && p.price <= priceRange[1]);
    if (selectedConditions.length) list = list.filter((p) => selectedConditions.includes(p.condition));
    if (selectedBrands.length) list = list.filter((p) => selectedBrands.includes(p.brand));
    if (searchQuery) list = list.filter((p) => p.name.toLowerCase().includes(searchQuery.toLowerCase()));
    if (sortOrder === "price_asc") list.sort((a, b) => a.price - b.price);
    if (sortOrder === "price_desc") list.sort((a, b) => b.price - a.price);
    if (sortOrder === "name") list.sort((a, b) => a.name.localeCompare(b.name));
    if (sortOrder === "newest") list.reverse();
    return list;
  }, [selectedCategory, priceRange, selectedConditions, selectedBrands, searchQuery, sortOrder]);

  const totalPages = Math.ceil(filtered.length / PAGE_SIZE);
  const paginated = filtered.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

  function toggleCondition(c: string) {
    setSelectedConditions((prev) => prev.includes(c) ? prev.filter((x) => x !== c) : [...prev, c]);
    setCurrentPage(1);
  }

  function toggleBrand(b: string) {
    setSelectedBrands((prev) => prev.includes(b) ? prev.filter((x) => x !== b) : [...prev, b]);
    setCurrentPage(1);
  }

  function resetFilters() {
    setSelectedCategory("Toutes");
    setPriceRange([0, 25000]);
    setSelectedConditions([]);
    setSelectedBrands([]);
    setSearchQuery("");
    setSortOrder("default");
    setCurrentPage(1);
  }

  const bg = darkMode ? "bg-gray-950 text-gray-100" : "bg-gray-50 text-gray-900";
  const cardBg = darkMode ? "bg-gray-800 border-gray-700" : "bg-white border-gray-100";
  const sidebarBg = darkMode ? "bg-gray-900 border-gray-700" : "bg-white border-gray-100";
  const mutedText = darkMode ? "text-gray-400" : "text-gray-500";
  const headerBg = darkMode ? "bg-gray-900 border-gray-700" : "bg-white border-gray-200";
  const inputBg = darkMode
    ? "bg-gray-800 border-gray-600 text-gray-100 placeholder-gray-500"
    : "bg-gray-50 border-gray-200 text-gray-900 placeholder-gray-400";

  return (
    <div className={`min-h-screen ${bg} transition-colors duration-200`}>
      {/* Promo Banner */}
      <div className="bg-orange-500 text-white text-center text-sm py-2 font-medium">
        🔥 Livraison gratuite à Casablanca dès 2 000 MAD &nbsp;·&nbsp; Paiement en 3× sans frais
      </div>

      {/* Header */}
      <header className={`sticky top-0 z-50 ${headerBg} border-b shadow-sm`}>
        <div className="max-w-7xl mx-auto px-4 h-16 flex items-center gap-4">
          <a href="/" className="flex items-center gap-2 flex-shrink-0">
            <div className="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center">
              <Smartphone size={16} className="text-white" />
            </div>
            <span className="text-xl font-bold tracking-tight">
              Teli<span className="text-orange-500">phoni</span>
            </span>
          </a>

          <nav className="hidden md:flex items-center gap-1 ml-6">
            {["Accueil", "Boutique", "Promotions"].map((link) => (
              <a
                key={link}
                href="#"
                className={`px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
                  link === "Boutique"
                    ? "bg-orange-50 text-orange-600"
                    : `${mutedText} hover:text-gray-900`
                }`}
              >
                {link}
              </a>
            ))}
          </nav>

          <div className="flex-1" />

          {/* Search */}
          {searchOpen ? (
            <div className="flex items-center gap-2">
              <input
                autoFocus
                value={searchQuery}
                onChange={(e) => { setSearchQuery(e.target.value); setCurrentPage(1); }}
                placeholder="Rechercher un produit..."
                className={`w-52 px-3 py-1.5 rounded-lg border text-sm outline-none focus:ring-2 focus:ring-orange-400 ${inputBg}`}
              />
              <button onClick={() => { setSearchOpen(false); setSearchQuery(""); }} className={`p-1.5 rounded-md ${mutedText}`}>
                <X size={16} />
              </button>
            </div>
          ) : (
            <button onClick={() => setSearchOpen(true)} className={`p-2 rounded-lg hover:bg-gray-100 ${mutedText} transition-colors`}>
              <Search size={20} />
            </button>
          )}

          <button onClick={() => setDarkMode(!darkMode)} className={`p-2 rounded-lg hover:bg-gray-100 ${mutedText} transition-colors`}>
            {darkMode ? <Sun size={20} /> : <Moon size={20} />}
          </button>

          <button className="relative p-2 rounded-lg hover:bg-orange-50 transition-colors" onClick={() => {}}>
            <ShoppingCart size={22} className={cartCount > 0 ? "text-orange-500" : mutedText} />
            {cartCount > 0 && (
              <span className="absolute -top-0.5 -right-0.5 w-5 h-5 bg-orange-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                {cartCount > 9 ? "9+" : cartCount}
              </span>
            )}
          </button>
        </div>
      </header>

      {/* Main */}
      <main className="max-w-7xl mx-auto px-4 py-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold">Boutique</h1>
          <p className={`text-sm mt-1 ${mutedText}`}>
            {filtered.length} produit{filtered.length !== 1 ? "s" : ""}
          </p>
        </div>

        <div className="flex gap-6">
          {/* Sidebar */}
          <aside className="hidden lg:block w-60 flex-shrink-0">
            <div className={`${sidebarBg} border rounded-xl p-5 sticky top-24 space-y-5`}>
              <div className="flex items-center justify-between">
                <span className="font-semibold text-xs uppercase tracking-widest">Filtres</span>
                <button onClick={resetFilters} className="text-xs text-orange-500 hover:underline">
                  Réinitialiser
                </button>
              </div>

              <Separator />

              {/* Categories */}
              <div>
                <p className="text-xs font-semibold uppercase tracking-wider mb-3 opacity-60">Catégories</p>
                <div className="space-y-1">
                  {[{ label: "Toutes", count: 21 }, { label: "Laptops", count: 8 }, { label: "Tablettes", count: 13 }].map(({ label, count }) => (
                    <button
                      key={label}
                      onClick={() => { setSelectedCategory(label); setCurrentPage(1); }}
                      className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors ${
                        selectedCategory === label
                          ? "bg-orange-500 text-white font-medium"
                          : `${mutedText} hover:bg-gray-50`
                      }`}
                    >
                      <span>{label}</span>
                      <span className={`text-xs px-1.5 py-0.5 rounded-full font-medium ${selectedCategory === label ? "bg-white/20 text-white" : "bg-gray-100 text-gray-500"}`}>
                        {count}
                      </span>
                    </button>
                  ))}
                </div>
              </div>

              <Separator />

              {/* Price */}
              <div>
                <p className="text-xs font-semibold uppercase tracking-wider mb-3 opacity-60">Prix (MAD)</p>
                <Slider min={0} max={25000} step={500} value={priceRange} onValueChange={(v) => { setPriceRange(v); setCurrentPage(1); }} className="mb-3" />
                <div className="flex items-center gap-2 text-xs text-center">
                  <span className={`flex-1 py-1.5 rounded-lg border font-medium ${cardBg}`}>{priceRange[0].toLocaleString()}</span>
                  <span className={mutedText}>–</span>
                  <span className={`flex-1 py-1.5 rounded-lg border font-medium ${cardBg}`}>{priceRange[1].toLocaleString()}</span>
                </div>
              </div>

              <Separator />

              {/* Condition */}
              <div>
                <p className="text-xs font-semibold uppercase tracking-wider mb-3 opacity-60">État</p>
                <div className="space-y-2.5">
                  {["Neuf", "Comme Neuf", "Reconditionné"].map((c) => (
                    <label key={c} className="flex items-center gap-2.5 cursor-pointer">
                      <Checkbox
                        checked={selectedConditions.includes(c)}
                        onCheckedChange={() => toggleCondition(c)}
                        className="data-[state=checked]:bg-orange-500 data-[state=checked]:border-orange-500"
                      />
                      <span className={`text-sm ${mutedText}`}>{c}</span>
                    </label>
                  ))}
                </div>
              </div>

              <Separator />

              {/* Brands */}
              <div>
                <p className="text-xs font-semibold uppercase tracking-wider mb-3 opacity-60">Marque</p>
                <div className="space-y-2.5">
                  {["Samsung", "Xiaomi/Redmi", "HP"].map((b) => (
                    <label key={b} className="flex items-center gap-2.5 cursor-pointer">
                      <Checkbox
                        checked={selectedBrands.includes(b)}
                        onCheckedChange={() => toggleBrand(b)}
                        className="data-[state=checked]:bg-orange-500 data-[state=checked]:border-orange-500"
                      />
                      <span className={`text-sm ${mutedText}`}>{b}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>
          </aside>

          {/* Product area */}
          <div className="flex-1 min-w-0">
            {/* Toolbar */}
            <div className="flex items-center gap-3 mb-5">
              <Select value={sortOrder} onValueChange={(v) => { setSortOrder(v); setCurrentPage(1); }}>
                <SelectTrigger className={`w-48 text-sm h-9 ${inputBg} border`}>
                  <SelectValue placeholder="Trier par" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="default">Par défaut</SelectItem>
                  <SelectItem value="newest">Plus récents</SelectItem>
                  <SelectItem value="price_asc">Prix croissant ↑</SelectItem>
                  <SelectItem value="price_desc">Prix décroissant ↓</SelectItem>
                  <SelectItem value="name">Nom A–Z</SelectItem>
                </SelectContent>
              </Select>

              <div className="flex-1" />

              <div className={`flex items-center p-1 rounded-lg border ${sidebarBg}`}>
                <button onClick={() => setViewMode("grid")} className={`p-1.5 rounded-md transition-colors ${viewMode === "grid" ? "bg-orange-500 text-white" : mutedText}`}>
                  <Grid3X3 size={15} />
                </button>
                <button onClick={() => setViewMode("list")} className={`p-1.5 rounded-md transition-colors ${viewMode === "list" ? "bg-orange-500 text-white" : mutedText}`}>
                  <List size={15} />
                </button>
              </div>
            </div>

            {/* Products */}
            {viewMode === "grid" ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                {paginated.map((p) => (
                  <ProductCard key={p.id} product={p} cardBg={cardBg} mutedText={mutedText} onAddToCart={() => setCartCount((c) => c + 1)} />
                ))}
              </div>
            ) : (
              <div className="space-y-3">
                {paginated.map((p) => (
                  <ListCard key={p.id} product={p} cardBg={cardBg} mutedText={mutedText} onAddToCart={() => setCartCount((c) => c + 1)} />
                ))}
              </div>
            )}

            {filtered.length === 0 && (
              <div className="text-center py-24">
                <p className="text-5xl mb-4">🔍</p>
                <p className="font-semibold text-lg">Aucun produit trouvé</p>
                <p className={`text-sm mt-1 mb-5 ${mutedText}`}>Essayez de modifier vos filtres</p>
                <button onClick={resetFilters} className="px-5 py-2 bg-orange-500 text-white rounded-lg text-sm font-semibold hover:bg-orange-600 transition-colors">
                  Réinitialiser les filtres
                </button>
              </div>
            )}

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 mt-10">
                <button onClick={() => setCurrentPage((p) => Math.max(1, p - 1))} disabled={currentPage === 1} className={`p-2 rounded-lg border transition-colors disabled:opacity-40 ${cardBg}`}>
                  <ChevronLeft size={16} />
                </button>
                {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                  <button
                    key={page}
                    onClick={() => setCurrentPage(page)}
                    className={`w-9 h-9 rounded-lg text-sm font-medium transition-colors border ${
                      currentPage === page ? "bg-orange-500 text-white border-orange-500" : `${cardBg} ${mutedText} hover:border-orange-300`
                    }`}
                  >
                    {page}
                  </button>
                ))}
                <button onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))} disabled={currentPage === totalPages} className={`p-2 rounded-lg border transition-colors disabled:opacity-40 ${cardBg}`}>
                  <ChevronRight size={16} />
                </button>
              </div>
            )}
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className={`mt-16 border-t ${headerBg}`}>
        <div className="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <div className="flex items-center gap-2 mb-3">
              <div className="w-7 h-7 bg-orange-500 rounded-md flex items-center justify-center">
                <Smartphone size={14} className="text-white" />
              </div>
              <span className="font-bold">Teli<span className="text-orange-500">phoni</span></span>
            </div>
            <p className={`text-sm ${mutedText} leading-relaxed`}>
              Votre boutique en ligne pour les meilleurs produits tech au Maroc.
            </p>
          </div>
          <div>
            <p className="font-semibold text-xs uppercase tracking-wider mb-3 opacity-60">Navigation</p>
            <ul className="space-y-2">
              {["Accueil", "Boutique", "Promotions"].map((l) => (
                <li key={l}><a href="#" className={`text-sm ${mutedText} hover:text-orange-500 transition-colors`}>{l}</a></li>
              ))}
            </ul>
          </div>
          <div>
            <p className="font-semibold text-xs uppercase tracking-wider mb-3 opacity-60">Contact</p>
            <p className={`text-sm ${mutedText} mb-4`}>📍 Casablanca, Maroc</p>
            <div className="flex gap-2">
              {[{ Icon: Camera, label: "Instagram" }, { Icon: Globe, label: "Facebook" }, { Icon: Phone, label: "WhatsApp" }].map(({ Icon, label }) => (
                <a key={label} href="#" title={label} className={`w-9 h-9 rounded-lg border flex items-center justify-center ${mutedText} hover:text-orange-500 hover:border-orange-300 transition-colors ${cardBg}`}>
                  <Icon size={16} />
                </a>
              ))}
            </div>
          </div>
        </div>
        <div className={`border-t px-4 py-4 max-w-7xl mx-auto flex items-center justify-between`}>
          <p className={`text-xs ${mutedText}`}>© 2026 Teliphoni. Tous droits réservés.</p>
          <div className="flex gap-4">
            {["Confidentialité", "CGV"].map((t) => (
              <a key={t} href="#" className={`text-xs ${mutedText} hover:text-orange-500 transition-colors`}>{t}</a>
            ))}
          </div>
        </div>
      </footer>
    </div>
  );
}

function ProductCard({ product, cardBg, mutedText, onAddToCart }: { product: Product; cardBg: string; mutedText: string; onAddToCart: () => void }) {
  const cond = CONDITION_STYLES[product.condition] ?? CONDITION_STYLES["Neuf"];
  const grad = BRAND_GRADIENTS[product.brand] ?? "from-gray-400 to-gray-600";

  return (
    <div className={`group ${cardBg} border rounded-xl overflow-hidden transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5`}>
      <div className={`relative h-44 bg-gradient-to-br ${grad} flex items-center justify-center`}>
        <span className="text-6xl">{product.category === "Laptops" ? "💻" : "📱"}</span>
        <span className="absolute top-3 left-3 bg-black/30 backdrop-blur-sm text-white text-xs px-2 py-0.5 rounded-full font-medium">
          {product.category}
        </span>
        <span className={`absolute top-3 right-3 text-xs px-2 py-0.5 rounded-full font-semibold ${cond.bg}`}>
          {cond.text}
        </span>
      </div>
      <div className="p-4">
        <h3 className="text-sm font-semibold leading-snug line-clamp-2 mb-2 min-h-[2.5rem]">{product.name}</h3>
        <div className="flex flex-wrap gap-1.5 mb-3">
          {[product.ram, product.storage, product.connectivity].map((spec) => (
            <span key={spec} className={`text-xs px-2 py-0.5 rounded-full border ${mutedText}`}>{spec}</span>
          ))}
        </div>
        <div className="flex items-center justify-between mb-3">
          <span className="text-lg font-bold text-orange-500">{formatPrice(product.price)}</span>
        </div>
        <button
          onClick={onAddToCart}
          className="w-full flex items-center justify-center gap-2 py-2.5 bg-orange-500 hover:bg-orange-600 active:scale-95 text-white text-sm font-semibold rounded-lg transition-all"
        >
          <ShoppingCart size={15} />
          Ajouter au panier
        </button>
      </div>
    </div>
  );
}

function ListCard({ product, cardBg, mutedText, onAddToCart }: { product: Product; cardBg: string; mutedText: string; onAddToCart: () => void }) {
  const cond = CONDITION_STYLES[product.condition] ?? CONDITION_STYLES["Neuf"];
  const grad = BRAND_GRADIENTS[product.brand] ?? "from-gray-400 to-gray-600";

  return (
    <div className={`${cardBg} border rounded-xl p-4 flex items-center gap-4 hover:shadow-md transition-all`}>
      <div className={`w-20 h-20 flex-shrink-0 rounded-xl bg-gradient-to-br ${grad} flex items-center justify-center text-3xl`}>
        {product.category === "Laptops" ? "💻" : "📱"}
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-start gap-2 mb-1.5">
          <h3 className="text-sm font-semibold leading-snug flex-1">{product.name}</h3>
          <span className={`text-xs px-2 py-0.5 rounded-full font-semibold flex-shrink-0 ${cond.bg}`}>{cond.text}</span>
        </div>
        <div className="flex flex-wrap gap-1.5">
          {[product.ram, product.storage, product.connectivity].map((spec) => (
            <span key={spec} className={`text-xs px-2 py-0.5 rounded-full border ${mutedText}`}>{spec}</span>
          ))}
        </div>
      </div>
      <div className="flex-shrink-0 flex flex-col items-end gap-2">
        <span className="text-lg font-bold text-orange-500">{formatPrice(product.price)}</span>
        <button
          onClick={onAddToCart}
          className="flex items-center gap-1.5 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-lg transition-colors"
        >
          <ShoppingCart size={14} />
          Ajouter
        </button>
      </div>
    </div>
  );
}
