TO DO LIST.
Checkout.php kažkas su mokėjimu negerai. Užbaigt

Įdiek šiuos UX ir funkcionalumo patobulinimus
AJAX veiksmai (be perkrovimo):
Dabar paspaudus „Į krepšelį“ arba „♥“ (Wishlist), puslapis persikrauna. Tai vargina.
Patobulinimas: Naudokite JavaScript (fetch API), kad šie veiksmai vyktų fone, o ikonėlės/skaičiai atsinaujintų dinamiškai.

Registracija/Prisijungimas perkant:
Suteikite galimybę pirkti be registracijos („Svečio režimas“), jei to dar nėra pilnai įgyvendinta. Privaloma registracija dažnai atbaido pirkėjus.

Sukurk duomenų bazės lentelę ir formą produktų atsiliepimams (su žvaigždutėmis).
Forume ir turgelyje prie įrašų pridėk mygtuką „Pranešti“ (Report), kad administratorius gautų pranešimą apie netinkamą turinį.“

Modifikuok callback.php failą (mokėjimo patvirtinimą).
Gavus sėkmingą apmokėjimo patvirtinimą, sistema privalo automatiškai sumažinti nupirktų prekių kiekį (quantity) duomenų bazėje.

SEO Meta duomenys:
Failuose product.php ir news_view.php suprogramuok dinaminį OpenGraph (og:image, og:title, og:description) ir Twitter Card meta žymų generavimą pagal konkretų turinį

2. Skaitmeniniai įrankiai diabetui
Svetainė gali tapti ne tik parduotuve, bet ir kasdieniu įrankiu.

Angliavandenių skaičiuoklė: Prie kiekvieno recepto ar produkto automatiškai rodyti ne tik kainą, bet ir „Angliavandenių vienetus“ (AV) ar glikemijos indeksą (GI). Galima sukurti paprastą skaičiuoklę, kur vartotojas įveda savo suvalgytą kiekį, o sistema paskaičiuoja bendrą angliavandenių kiekį.
Glikemijos dienoraštis: Paprasta forma vartotojo paskyroje, kur jis gali įsirašyti savo rodiklius ir matyti grafiką. Tai labai padidina lankytojų grįžtamumą.

Jau turite forumą ir turgelį – išnaudokite tai aktyvumui skatinti.
Lojalumo taškai („Cukrinukai“): Skirkite taškų ne tik už pirkinius, bet ir už:
Įkeltą receptą.
Parašytą atsiliepimą apie prekę.
Aktyvumą forume (pvz., geriausias savaitės atsakymas).
Nauda: Taškus galima panaudoti nuolaidoms krepšelyje. Tai skatins žmones ne tik pirkti, bet ir kurti turinį jūsų svetainėje.

Dovanų kuponai
Elektroniniai kuponai, kuriuos galima nupirkti ir išsiųsti draugui.

Jau turite turgelį (community_market.php), bet galite jį plėsti.
Dovanoti: Atskira kategorija turgelyje, skirta tiems, kurie turi likusių nepanaudotų, bet galiojančių priemonių (pvz., pakeitė pompą ir liko senų adatų) ir nori jas atiduoti tiems, kam sunku finansiškai. Tai labai stiprina bendruomenės jausmą.

Padarykite svetainę Progressive Web App (PWA). Tai leistų vartotojams įsidiegti „Cukrinuką“ į telefoną kaip programėlę (be App Store). Tai ypač naudinga, jei įdiegsite angliavandenių skaičiuoklę ar receptų paiešką – vartotojas tai turės visada po ranka, net ir būdamas parduotuvėje.

Nemokamo pristatymo „Progress Bar“
Idėja: Krepšelyje vizualiai parodyti, kiek trūksta iki nemokamo pristatymo. Tai psichologiškai skatina pridėti dar vieną prekę.
Kaip įgyvendinti:
Faile cart.php jau turite logiką, tikrinančią free_over reikšmę.
Pridėkite HTML/CSS juostą virš prekių sąrašo: Liko vos 12.50 € iki nemokamo pristatymo!.

Likučių valdymas realiu laiku
Idėja: Jei prekės likutis mažas (pvz., < 5 vnt.), prekės kortelėje rodykite „Liko tik keli vienetai!“.
Kaip įgyvendinti:
Faile product.php patikrinkite $product['quantity'].
Jei $product['quantity'] > 0 && $product['quantity'] < 5, atvaizduokite įspėjimą raudona spalva.

Patikimas narys“ statusas
Idėja: Bendruomenės turguje (community_market.php) kyla pasitikėjimo klausimas.
Kaip įgyvendinti:
Sukurkite sistemą, kur po sėkmingo sandorio (kai community_orders statusas tampa įvykdyta), pirkėjas gali palikti atsiliepimą (teigiamas/neigiamas).
Vartotojo profilyje rodykite „X sėkmingų sandorių“.

Privatūs pokalbiai realiuoju laiku (AJAX)
Situacija: Dabar messages.php veikia puslapio perkrovimo principu.
Idėja: Įdiegti paprastą JavaScript setInterval, kuris kas 5-10 sekundžių tikrintų naujas žinutes fone ir atnaujintų pokalbio langą be puslapio perkrovimo.

Mažo likučio ataskaita
Idėja: admin.php skydelyje, „Dashboard“ skiltyje, pridėti lentelę „Prekės, kurios baigiasi“.
Kaip įgyvendinti:
SQL užklausa: SELECT title, quantity FROM products WHERE quantity < 10 ORDER BY quantity ASC.
Tai leis operatyviau užsakyti papildymą.

Masinis nuotraukų įkėlimas (Drag & Drop)
Situacija: Dabar product_edit.php naudoja standartinį input type="file" multiple.
Idėja: Panaudoti paprastą JS biblioteką (arba „vanilla“ JS) sukurti „drop zone“, kur galėtumėte tiesiog įmesti nuotraukas, ir jos būtų įkeliamos per AJAX, iškart parodant peržiūrą.

Socialinis prisijungimas (Social Login)
Dabar turite tik standartinę registraciją el. paštu. Dauguma vartotojų nori prisijungti vienu paspaudimu.
Idėja: Leisti prisijungti su „Google“ arba „Facebook“.
Kaip įgyvendinti:
Duomenų bazė: Į users lentelę pridėkite stulpelius google_id (VARCHAR) ir auth_provider.
Biblioteka: Naudokite Google API Client for PHP arba lengvesnę alternatyvą.
Logika: Faile login.php sukurkite mygtuką, kuris nukreipia į OAuth. Grįžus su sėkmingu token'u, patikrinkite, ar toks google_id arba email egzistuoja. Jei ne – sukurkite vartotoją automatiškai.

SEO optimizacija (Sitemap ir Meta duomenys)
Kad „Google“ geriau suprastų jūsų turinį.
Dinaminis Sitemap: Sukurkite sitemap.php, kuris generuoja XML failą su visomis nuorodomis į produktus, receptus ir naujienas realiu laiku.
Open Graph (OG) Tagai: layout.php jau turi headerStyles funkciją. Išplėskite ją, kad priimtų $meta masyvą (title, description, image).
Faile product.php paduokite prekės nuotrauką ir aprašymą į headerį, kad dalinantis Facebook'e rodytų gražią kortelę.



Turiu veikiančią custom PHP e-komercijos sistemą „Cukrinukas“ (diabeto prekės, receptai, bendruomenė). Noriu atlikti pažangius SEO optimizavimo ir Facebook Pixel integracijos darbus. Sistema nenaudoja karkasų (frameworks), viskas parašyta grynu PHP.
Tavo užduotis – pateikti tikslų kodą ir instrukcijas šiems funkcionalumams įdiegti.
1 UŽDUOTIS: SEO Optimizacija
1. Dinaminiai Meta Tagai ir Open Graph (layout.php ir puslapiai)
Modifikuok funkciją renderHeader faile layout.php. Ji turi priimti papildomą argumentą $meta (masyvas su title, description, image).
Jei $meta duomenys pateikti, naudok juos <title>, <meta name="description"> ir Open Graph (og:title, og:image, og:description) bei Twitter kortelių tagams. Jei ne – naudok default reikšmes.
Atnaujink product.php failą: suformuok $meta masyvą iš produkto duomenų (pavadinimas, kaina, aprašymas, nuotrauka) ir paduok jį į renderHeader.
2. Struktūruoti duomenys / Schema.org (product.php, recipe_view.php)
Į product.php failo apačią (prieš footerį) įdėk JSON-LD skriptą, aprašantį produktą (@type: Product), įskaitant kainą, likutį (InStock/OutOfStock) ir nuotrauką.
Į recipe_view.php įdėk JSON-LD skriptą receptams (@type: Recipe).
3. Dinaminis svetainės žemėlapis (sitemap.php)
Sukurk naują failą sitemap.php.
Jame naudok db.php prisijungimą.
Sugeneruok XML struktūrą, kuri automatiškai įtraukia:
Statinius puslapius (/, /news.php, /products.php ir kt.).
Visus aktyvius produktus iš DB (/product.php?id=X).
Visus receptus iš DB (/recipe_view.php?id=X).
Nustatyk tinkamą Content-Type: application/xml antraštę.
4. Draugiški URL (.htaccess)
Pateik .htaccess taisykles (RewriteRule), kad vietoje product.php?id=123 būtų galima naudoti e-kolekcija.lt/produktas/pavadinimas-123.
2 UŽDUOTIS: Facebook Pixel Integracija
1. Pagrindinis kodas (layout.php)
Įdėk Facebook Pixel „Base Code“ į layout.php failo <head> sekciją (arba renderHeader funkcijos pradžią), kad jis veiktų visuose puslapiuose.
2. Įvykių sekimas (Standard Events) Pateik kodą šiems failams:
product.php:
Puslapio apačioje įdėk fbq('track', 'ViewContent', ...) su produkto ID, kaina ir valiuta.
Ant mygtuko „Į krepšelį“ (<button>) pridėk onclick įvykį, kuris iššaukia fbq('track', 'AddToCart', ...) su tais pačiais produkto duomenimis.
cart.php:
Ant mygtuko „Apmokėti“ (kuris veda į checkout.php) pridėk onclick="fbq('track', 'InitiateCheckout');".
orders.php:
Sukurk logiką, kuri tikrina, ar vartotojas ką tik sėkmingai grįžo iš banko (pagal $_SESSION['flash_success'] arba kitą indikatorių).
Jei tai sėkmingas grįžimas, sugeneruok JS kodą fbq('track', 'Purchase', ...) su tikslia užsakymo suma ir ID.
3 UŽDUOTIS: Kiti patobulinimai
Lazy Loading: Instruktuok, kaip masiškai pridėti loading="lazy" atributą visoms produktų nuotraukoms products.php tinklelyje.
Breadcrumbs: Patikslink, kaip išplėsti „Breadcrumbs“ navigaciją product.php faile, kad ji rodytų pilną kelią (pvz., Pagrindinis > Parduotuvė > Kategorija > Prekė).
Svarbu: Pateikdamas kodą, nurodyk, į kurį failą ir kurią vietą jis turi būti įterptas. Naudok mano turimą failų struktūrą ir kintamųjų pavadinimus (pvz., $pdo, $product['title']).





1. UX ir Frontend interaktyvumas (AJAX ir PWA)
Užduotis: Įgyvendinti modernius vartotojo sąsajos sprendimus be puslapio perkrovimo ir PWA funkcionalumą.

Reikalavimai:

AJAX krepšelis ir Wishlist:

Modifikuoti mygtukus „Į krepšelį“ ir „♥“ (Wishlist) failuose products.php ir produktų kortelėse.

Naudoti JavaScript Fetch API, kad duomenys būtų siunčiami į serverį fone.

Sėkmingo atsakymo atveju dinamiškai atnaujinti krepšelio ikonėlę (prekių skaičių/sumą) ir širdelės spalvą header'yje be puslapio perkrovimo.

Realaus laiko pranešimai (Chat):

Faile messages.php įdiegti setInterval (kas 5–10 sek.) arba WebSocket sprendimą.

JavaScript turi tikrinti naujas žinutes fone ir papildyti pokalbio langą (append), kad nereikėtų rankiniu būdu perkrauti puslapio.

Nemokamo pristatymo „Progress Bar“:

Faile cart.php sukurti vizualią juostą (progress bar).

Logika: Paimti cart_total ir palyginti su free_shipping_treshold.

Atvaizduoti tekstą: „Liko vos X € iki nemokamo pristatymo!“ ir atitinkamai užpildyti juostą CSS pagalba.

PWA (Progressive Web App):

Sugeneruoti manifest.json failą (pavadinimas, ikonėlės, spalvos).

Sukurti ir registruoti service-worker.js failą, kad svetainę būtų galima įdiegti į telefoną kaip programėlę ir ji veiktų offline režimu (bent jau statiniai puslapiai).

2. Prekių valdymas, Paieška ir Likučiai
Užduotis: Patobulinti prekių paiešką, likučių valdymą ir atvaizdavimą.

Reikalavimai:


Likučių valdymas (Backend):

Faile callback.php (mokėjimų vartai): gavus patvirtinimą apie sėkmingą apmokėjimą (status='accepted'), automatiškai vykdyti SQL UPDATE products SET quantity = quantity - ordered_qty WHERE id = product_id.

Likučių atvaizdavimas (Frontend):

Faile product.php tikrinti kintamąjį $product['quantity'].

Sąlyga: Jei likutis > 0 ir < 5, rodyti ryškų pranešimą: „Liko tik keli vienetai!“.

Mažo likučio ataskaita (Admin):

Faile admin.php (Dashboard skiltyje) sukurti naują valdiklį/lentelę.

SQL: SELECT title, quantity FROM products WHERE quantity < 10 ORDER BY quantity ASC. Tai leis administratoriui matyti, ką reikia užsakyti.

3. Vartotojų sistema, Lojalumas ir Saugumas
Užduotis: Išplėsti vartotojų registracijos galimybes, sukurti lojalumo sistemą ir „Svečio“ režimą.

Reikalavimai:

Svečio pirkimas (Guest Checkout):

Krepšelyje (cart.php -> checkout.php) leisti tęsti pirkimą neprisijungus.

Tokiu atveju prašyti tik pristatymo informacijos ir el. pašto, nekuriant vartotojo paskyros DB, arba kuriant laikiną „šešėlinę“ paskyrą užsakymo įvykdymui.

Socialinis prisijungimas (OAuth):

Įdiegti prisijungimą per „Google“ ir „Facebook“.

DB pakeitimas: ALTER TABLE users ADD COLUMN google_id VARCHAR(255), ADD COLUMN auth_provider VARCHAR(50).

Logika: Jei el. paštas jau egzistuoja, susieti paskyras; jei ne – sukurti naują vartotoją.

Lojalumo sistema „Cukrinukai“:

DB pakeitimas: users lentelėje pridėti stulpelį points_balance.

Logika:

Skirti taškus už pirkimą (pvz., 1€ = 1 taškas).

Skirti taškus už turinį: +X taškų už patvirtintą receptą, +Y už atsiliepimą.

Krepšelyje leisti panaudoti taškus kaip nuolaidą.

Patikimo nario statusas (Reputacija):

Turgelyje (community_market.php) po įvykdyto sandorio leisti pirkėjui palikti atsiliepimą (teigiamas/neigiamas).

Vartotojo profilyje rodyti statistiką: „X sėkmingų sandorių“.

4. Bendruomenė, Turinys ir SEO
Užduotis: Sukurti įrankius diabeto valdymui, atsiliepimų sistemą ir optimizuoti SEO.

Reikalavimai:

Atsiliepimai ir Moderavimas:

Sukurti DB lentelę product_reviews (user_id, product_id, rating, comment, date).

Produkto puslapyje atvaizduoti formą ir esamus atsiliepimus.

Forume ir turgelyje prie įrašų pridėti mygtuką „Pranešti“ (Report). Sukurti lentelę reports, kurią matytų administratorius.

Diabeto įrankiai (Skaičiuoklė ir Dienoraštis):

Angliavandenių skaičiuoklė: Receptuose ir produktuose įvesti laukus „Angliavandenių kiekis (100g)“. Sukurti skriptą, kuris vartotojui įvedus svorį, paskaičiuotų galutinius angliavandenius (arba AV).

Glikemijos dienoraštis: Vartotojo profilyje sukurti skiltį, kurioje galima įrašyti: Data, Laikas, Glikemija (mmol/l). Atvaizduoti paprastą grafiką (naudojant pvz., Chart.js).

SEO Meta duomenys (OpenGraph):

Faile layout.php (header dalyje) dinamiškai generuoti <meta property="og:..." žymas.

Jei atidaromas product.php arba news_view.php, meta og:image turi būti to produkto/naujienos nuotrauka, o og:title – pavadinimas.

Sukurti sitemap.php, kuris dinamiškai generuoja XML su visais aktyviais produktais ir straipsniais.

Dovanų ir Labdaros sistema:

Dovanų kuponai: Sukurti virtualų produktą, kurį nupirkus generuojamas unikalus kodas, suteikiantis X sumos nuolaidą.

Turgelio kategorija „Dovanoju“: Pridėti atskirą filtrą turgelyje prekėms, kurių kaina 0.00 €, skirtą bendruomenės paramos mainams.

5. Admin UI patobulinimas (Drag & Drop)
Užduotis: Supaprastinti nuotraukų įkėlimą administracinėje dalyje.

Reikalavimai:

Faile product_edit.php pakeisti standartinį input type="file" į „Drag & Drop“ zoną.

Naudoti JavaScript (arba biblioteką kaip Dropzone.js), kad nuotraukos būtų įkeliamos asinchroniškai (AJAX), o administratorius iškart matytų įkeltų nuotraukų peržiūrą (thumbnails) prieš išsaugodamas prekę.
