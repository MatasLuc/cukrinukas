# Cukrinukas – Projekto Vystymo Planas (To-Do)

Šiame dokumente pateikiamas sąrašas planuojamų funkcionalumų ir patobulinimų.

## 1. Skubūs Taisymai (Critical)
- [ ] **Apmokėjimo sistema:** Peržiūrėti `checkout.php` ir `libwebtopay` logiką – užtikrinti sklandų mokėjimo iniciavimą ir statusų atnaujinimą.

## 2. Vartotojo Patirtis (UX/UI)
- [ ] **AJAX veiksmai:**
    - Prekių įdėjimas į krepšelį be puslapio perkrovimo.
    - „Norų sąrašo“ (Wishlist) veiksmai be puslapio perkrovimo.
    - Dinaminis krepšelio ikonėlės/skaičiaus atnaujinimas header'yje.
- [ ] **Nemokamo pristatymo juosta:** Krepšelyje atvaizduoti „Progress Bar“, rodantį, kiek eurų trūksta iki nemokamo pristatymo.
- [ ] **Likučių atvaizdavimas:** Prekės kortelėje rodyti įspėjimą (pvz., raudona spalva), kai likutis yra mažas (pvz., < 5 vnt.).
- [ ] **PWA (Progressive Web App):** Pritaikyti svetainę diegimui į telefonus (manifest.json, service workers), kad veiktų kaip programėlė ir turėtų offline galimybes.

## 3. Parduotuvės Funkcionalumas
- [ ] **Svečio pirkimas:** Suteikti galimybę pirkti be privalomos registracijos (Guest Checkout).
- [ ] **Atsiliepimų sistema:**
    - Sukurti DB lentelę atsiliepimams.
    - Sukurti formą prie prekių su vertinimu žvaigždutėmis ir komentaru.
- [ ] **Lojalumo sistema („Cukrinukai“):**
    - Skirti taškus už pirkinius, registraciją, įkeltus receptus ar atsiliepimus.
    - Leisti panaudoti taškus nuolaidoms krepšelyje.
- [ ] **Dovanų kuponai:** Galimybė įsigyti ir panaudoti elektroninius dovanų kuponus.

## 4. Bendruomenė ir Turgelis
- [ ] **Turinio moderavimas:** Pridėti mygtuką „Pranešti“ (Report) prie forumo temų ir turgelio skelbimų netinkamam turiniui žymėti.
- [ ] **Kategorija „Dovanoju“:** Turgelyje sukurti atskirą skiltį/filtrą atiduodamoms priemonėms (kaina 0.00 €).
- [ ] **Narių reputacija:**
    - Įdiegti „Patikimo nario“ statusą.
    - Leisti palikti atsiliepimą po sėkmingo sandorio turgelyje.
    - Profilyje rodyti sėkmingų sandorių skaičių.
- [ ] **Privačios žinutės (Live):** Patobulinti `messages.php` naudojant AJAX/setInterval, kad susirašinėjimas vyktų realiu laiku be perkrovimo.

## 5. Skaitmeniniai Įrankiai Diabetui
- [ ] **Angliavandenių skaičiuoklė:**
    - Prie produktų/receptų rodyti angliavandenių vienetus (AV).
    - Sukurti įrankį, kur įvedus svorį paskaičiuojamas bendras AV kiekis.
- [ ] **Glikemijos dienoraštis:** Vartotojo paskyroje sukurti formą ir grafiką rodiklių sekimui.

## 6. Administravimas
- [ ] **Likučių ataskaita:** Admin skydelyje („Dashboard“) pridėti lentelę „Prekės, kurios baigiasi“, kad būtų galima laiku užsakyti papildymą.
- [ ] **Masinis nuotraukų įkėlimas:** Patobulinti prekių redagavimą (`product_edit.php`) įdiegiant „Drag & Drop“ zoną nuotraukoms su AJAX įkėlimu ir peržiūra.

## 7. Autentifikacija
- [ ] **Socialinis prisijungimas:** Įdiegti prisijungimą per „Google“ ir „Facebook“ (OAuth).
