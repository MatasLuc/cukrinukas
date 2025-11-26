# Cukrinukas – Projekto Būsena ir Vystymo Planas

Šiame dokumente pateikiama išsami projekto apžvalga: kas jau yra įgyvendinta (testavimui) ir kas dar planuojama (vystymui).

---

## ✅ 1. Atlikti Darbai (Ready for Testing)
Šios funkcijos jau yra įdiegtos kode. Prašome patikrinti jų veikimą.

### 🔍 SEO Optimizacija
- [x] **Dinaminiai Meta Tagai:** - `layout.php` automatiškai generuoja `<title>`, `description` pagal puslapio turinį.
  - Įdiegti **Open Graph** (Facebook) ir **Twitter Card** tagai gražiam dalinimuisi socialiniuose tinkluose.
- [x] **Struktūruoti duomenys (Schema.org):**
  - `product.php`: Google supranta prekės kainą, valiutą ir likutį.
  - `recipe_view.php`: Google supranta recepto autorių, pavadinimą ir datą.
- [x] **Techninis SEO:**
  - Sukurtas dinaminis `sitemap.php` (XML žemėlapis paieškos sistemoms).
  - Sukurtas `.htaccess` failas „draugiškoms“ nuorodoms (pvz., `/produktas/pavadinimas-123`).
  - Įjungtas **Lazy Loading** nuotraukoms kataloge (`products.php`) ir prekės puslapyje.
  - Išplėsta „Breadcrumbs“ navigacija prekės puslapyje.

### 📊 Facebook Pixel Integracija
- [x] **Base Code:** Įdėtas į `layout.php` (veikia visuose puslapiuose).
- [x] **Įvykių sekimas (Standard Events):**
  - `PageView`: Visi puslapiai.
  - `ViewContent`: Atidarius konkrečią prekę (`product.php`).
  - `AddToCart`: Paspaudus mygtuką „Į krepšelį“ (`product.php`).
  - `InitiateCheckout`: Paspaudus „Apmokėti“ krepšelyje (`cart.php`).
  - `Purchase`: Sėkmingai grįžus iš banko (`orders.php`).

---

## 🚧 2. Planuojami Darbai (To-Do List)

### 🚨 Skubūs Taisymai (Critical)
- [ ] **Apmokėjimo sistema:** Peržiūrėti `checkout.php` ir `libwebtopay` logiką – užtikrinti sklandų mokėjimo iniciavimą ir statusų atnaujinimą.

### 🎨 Vartotojo Patirtis (UX/UI)
- [ ] **AJAX veiksmai (Be perkrovimo):**
    - Prekių įdėjimas į krepšelį.
    - „Norų sąrašo“ (Wishlist) paspaudimas.
    - Dinaminis krepšelio skaičiuko atnaujinimas header'yje.
- [ ] **Nemokamo pristatymo juosta:** Krepšelyje atvaizduoti „Progress Bar“, rodantį, kiek eurų trūksta iki nemokamo pristatymo.
- [ ] **Likučių atvaizdavimas:** Prekės kortelėje rodyti įspėjimą (pvz., raudona spalva), kai likutis yra mažas (pvz., < 5 vnt.).
- [ ] **PWA (Progressive Web App):** Pritaikyti svetainę diegimui į telefonus (manifest.json, service workers) veikimui offline.

### 🛒 Parduotuvės Funkcionalumas
- [ ] **Svečio pirkimas:** Leisti pirkti be privalomos registracijos (Guest Checkout).
- [ ] **Atsiliepimų sistema:** Sukurti DB lentelę ir formą vertinimams žvaigždutėmis bei komentarams.
- [ ] **Lojalumo sistema („Cukrinukai“):** Kaupiamieji taškai už pirkinius/veiksmus ir jų panaudojimas nuolaidoms.
- [ ] **Dovanų kuponai:** Galimybė įsigyti ir panaudoti elektroninius dovanų kuponus.

### 👥 Bendruomenė ir Turgelis
- [ ] **Turinio moderavimas:** Mygtukas „Pranešti“ (Report) netinkamam turiniui.
- [ ] **Kategorija „Dovanoju“:** Turgelyje atskiras filtras prekėms, kurių kaina 0.00 €.
- [ ] **Narių reputacija:** Reitingavimo sistema po sėkmingų sandorių.
- [ ] **Privačios žinutės (Live):** `messages.php` atnaujinimas realiu laiku (AJAX/WebSocket).

### 🩸 Skaitmeniniai Įrankiai Diabetui
- [ ] **Angliavandenių skaičiuoklė:** Įrankis AV (angliavandenių vienetų) skaičiavimui pagal produkto svorį.
- [ ] **Glikemijos dienoraštis:** Vartotojo paskyros skiltis rodiklių sekimui ir grafikai.

### 🛠️ Administravimas
- [ ] **Likučių ataskaita:** Admin skydelyje lentelė „Prekės, kurios baigiasi“.
- [ ] **Masinis nuotraukų įkėlimas:** Drag & Drop zona prekių redagavime (`product_edit.php`).

### 🔐 Autentifikacija
- [ ] **Socialinis prisijungimas:** Google ir Facebook (OAuth) integracija.
