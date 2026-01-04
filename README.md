# Cukrinukas – E-komercijos ir Bendruomenės Platforma

**Cukrinukas** yra specializuota internetinė parduotuvė ir bendruomenės platforma, sukurta naudojant „gryną“ (native) **PHP**, orientuota į desertus, sveiką mitybą bei diabetui draugiškus produktus. Projektas apjungia elektroninę prekybą, receptų dalinimąsi ir vartotojų bendruomenę.

---

## 🚀 Pagrindinis Funkcionalumas

### 🛒 El. Parduotuvė (`products.php`, `cart.php`, `checkout.php`)
Pilnai veikianti e-komercijos sistema:
- **Prekių katalogas:** Filtravimas pagal kategorijas, paieška realiu laiku, „Lazy Loading“ nuotraukoms.
- **Prekės kortelė:** Išsamus aprašymas, nuotraukų galerija, susijusios prekės, likučių atvaizdavimas.
- **Krepšelis ir Pirkimas:**
  - Prekių krepšelio valdymas (kiekio keitimas, šalinimas).
  - Integruotas **Paysera (libwebtopay)** mokėjimų modulis.
  - Užsakymų istorija ir statusų sekimas vartotojo paskyroje (`orders.php`).
- **Norų sąrašas (Wishlist):** Galimybė išsaugoti patikusias prekes vėlesniam laikui (`saved.php`).
- **Nuolaidų sistema:** Globalios nuolaidos ir kategorijų nuolaidos, valdomos per admin panelę.

### 🍽️ Receptų Sistema (`recipes.php`, `recipe_view.php`)
Turinio kūrimo ir dalinimosi modulis:
- **Receptų katalogas:** Vizualus receptų sąrašas su "Naujiena" žymomis.
- **Struktūruoti duomenys:** Automatinis **Schema.org/Recipe** generavimas (Google Rich Snippets).
- **Interakcijos:** Vartotojai gali išsisaugoti receptus į savo paskyrą (Mėgstamiausi).
- **Kūrimas:** Galimybė kurti ir redaguoti receptus (Admin/Moderatoriams).

### 👥 Bendruomenė ir Turgelis (`community.php`)
Erdvė narių bendravimui:
- **Diskusijos:** Forumo tipo susirašinėjimas įvairiomis temomis (`community_discussions.php`).
- **Turgelis:** Vartotojų tarpusavio prekybos/mainų skelbimų lenta (`community_market.php`).
- **Saugumas:** Taisyklės ir moderavimo įrankiai netinkamo turinio kontrolei.

### 🔐 Vartotojų Sistema
- **Autentifikacija:** Registracija, prisijungimas, slaptažodžio atkūrimas (`forgot_password.php`).
- **Paskyra:** Vartotojo profilio valdymas, užsakymų istorija, išsaugoti receptai ir prekės.
- **Rolės:** Administratoriaus ir paprasto vartotojo teisės (`security.php`).

---

## 🛠️ Techniniai Sprendimai ir SEO

### 🔍 SEO Optimizacija
Projektas yra stipriai optimizuotas paieškos sistemoms:
- **Friendly URLs:** Naudojamas `.htaccess` gražioms nuorodoms (pvz., `/produktas/pavadinimas`).
- **Dinaminiai Meta Tagai:** Automatiškai generuojami `<title>`, `description` ir **Open Graph** (Facebook/Twitter) duomenys `layout.php`.
- **Sitemap:** Automatiškai generuojamas `sitemap.php` XML formatu.
- **Greitaveika:** Optimizuotas paveikslėlių krovimas ir CSS/JS minimizavimas.

### 📱 PWA (Progressive Web App)
Svetainė pritaikyta veikimui mobiliuosiuose įrenginiuose ir offline režimu:
- **Manifest:** `manifest.json` leidžia įdiegti svetainę kaip programėlę į telefoną.
- **Service Worker:** `service-worker.js` kešuoja pagrindinius failus (CSS, JS, Fonts) ir užtikrina veikimą be interneto (rodomas `offline.php`).

### ⚙️ Administravimas (`/admin`)
Išsamus valdymo pultas savininkui:
- **Dashboard:** Pardavimų statistika, naujausi užsakymai, vartotojų skaičius (`hero_stats.php`).
- **Turinio valdymas:** Prekių, kategorijų, receptų, naujienų ir DUK redagavimas.
- **Užsakymų valdymas:** Statusų keitimas, sąskaitų peržiūra.
- **Nustatymai:** Dizaino, meniu ir pristatymo būdų konfigūracija.

### 💻 Naudojamos Technologijos
- **Backend:** PHP 8+ (PDO Database Connection).
- **Database:** MySQL / MariaDB.
- **Frontend:** HTML5, CSS3 (Custom Variables + Flexbox/Grid), Vanilla JS.
- **Libraries:**
  - `PHPMailer` – laiškų siuntimui.
  - `libwebtopay` – Paysera mokėjimų integracijai.

---

## 📂 Projekto Struktūra

/ ├── admin/ # Administratoriaus valdymo pulto failai ├── lib/ # Išorinės bibliotekos (PHPMailer) ├── libwebtopay/ # Mokėjimų sistemos biblioteka ├── uploads/ # Vartotojų ir prekių nuotraukos ├── .htaccess # Maršrutizavimo taisyklės ├── db.php # Duomenų bazės prisijungimas ├── layout.php # Pagrindinis šablonas (Header/Footer/SEO) ├── service-worker.js # PWA funkcionalumas ├── index.php # Pagrindinis puslapis ├── products.php # Parduotuvės katalogas ├── recipes.php # Receptų katalogas └── ... (kiti failai)


---

## ✅ Įgyvendinimo Būsena (Status)

### Atlikta (Ready)
- [x] Pilna el. parduotuvės logika (Prekės, Krepšelis, Užsakymai).
- [x] Paysera mokėjimų integracija (`libwebtopay`).
- [x] SEO optimizacija (Schema.org, Meta tags, Sitemap).
- [x] PWA bazinis funkcionalumas (Installable, Offline page).
- [x] Vartotojų registracija ir profiliai.
- [x] Receptų sistema su išsaugojimo funkcija.
- [x] Admin panelė su statistika ir turinio valdymu.
- [x] Bendruomenės (Community) puslapiai.

### Planuojama (To-Do / Improvements)
- [ ] **AJAX krepšelis:** Prekių įdėjimas be puslapio perkrovimo.
- [ ] **Live Chat:** Žinučių sistema tarp vartotojų (`messages.php` WebSocket).
- [ ] **Diabeto įrankiai:** Angliavandenių skaičiuoklė (Frontend dalis).
- [ ] **Guest Checkout:** Pirkimas be registracijos.
