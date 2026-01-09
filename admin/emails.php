<?php
// admin/emails.php

// Paimame vartotojų sąrašą
$stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    /* Paprasto redaktoriaus stilius */
    .simple-editor-wrapper {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        font-family: 'Inter', sans-serif;
    }
    .editor-toolbar {
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px;
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    .editor-btn {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        cursor: pointer;
        padding: 6px 12px;
        font-size: 14px;
        font-weight: 600;
        min-width: 36px;
        color: #4b5563;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .editor-btn:hover {
        background: #f1f5f9;
        color: #111827;
        border-color: #9ca3af;
    }
    #editor-visual {
        min-height: 450px;
        padding: 32px;
        outline: none;
        overflow-y: auto;
        font-family: 'Inter', Helvetica, Arial, sans-serif;
        font-size: 16px;
        line-height: 1.6;
        color: #475467; /* Atitinka mailer.php tekstą */
        background-color: #ffffff;
    }
    #editor-visual:focus {
        background-color: #fafafa;
    }
    /* Elementų stiliai pačiame redaktoriuje, kad matytųsi kaip laiške */
    #editor-visual h2 {
        color: #0f172a;
        font-weight: 700;
        margin-top: 0;
    }
    #editor-visual a {
        color: #2563eb;
        text-decoration: underline;
    }
    #editor-visual blockquote {
        border-left: 4px solid #2563eb;
        margin-left: 0;
        padding-left: 16px;
        color: #64748b;
        background: #f8fafc;
        padding: 16px;
        border-radius: 0 12px 12px 0;
        font-style: italic;
    }
    
    /* Formos elementai */
    .form-label {
        display: block; 
        margin-bottom: 8px; 
        font-weight: 600; 
        color: #374151;
        font-size: 14px;
    }
    .form-input, .form-select {
        width: 100%; 
        padding: 10px 12px; 
        border-radius: 8px; 
        border: 1px solid #d1d5db; 
        background-color: #fff; 
        font-size: 14px;
        font-family: inherit;
        transition: border-color 0.2s;
    }
    .form-input:focus, .form-select:focus {
        border-color: #2563eb;
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    
    /* Select grupavimas */
    optgroup { font-weight: 700; color: #2563eb; }
</style>

<div class="card" style="border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid #e5e7eb;">
        <h3 style="margin:0; color:#111827;">📧 Siųsti laišką</h3>
    </div>

    <form action="admin.php?view=emails" method="POST" onsubmit="syncContent(); return confirm('Ar tikrai norite siųsti šį laišką?');">
        <?php echo csrfField(); ?>
        
        <input type="hidden" name="action" value="send_email">
        
        <div class="grid grid-2" style="gap: 20px;">
            <div>
                <label class="form-label">Gavėjas</label>
                <select name="recipient_id" required class="form-select">
                    <option value="">-- Pasirinkite gavėją --</option>
                    
                    <option value="all" style="font-weight:bold; color:#2563eb;">📢 SIŲSTI VISIEMS KLIENTAMS (<?php echo count($users); ?>)</option>
                    <option disabled>--------------------------------</option>
                    
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label">Šablonas (greitas užpildymas)</label>
                <select id="templateSelector" class="form-select" style="background-color:#f8fafc;">
                    <option value="">-- Pasirinkite šabloną --</option>
                    
                    <optgroup label="✨ Bendra komunikacija">
                        <option value="welcome">👋 Sveiki atvykę (Registracija)</option>
                        <option value="order_shipped">📦 Užsakymas išsiųstas</option>
                        <option value="feedback">⭐ Atsiliepimo prašymas</option>
                        <option value="apology">😔 Atsiprašymas dėl vėlavimo</option>
                        <option value="restock">🔄 Prekė vėl prekyboje</option>
                    </optgroup>

                    <optgroup label="🔥 Pasiūlymai ir Akcijos">
                        <option value="promo">🎉 Bendras išpardavimas (-20%)</option>
                        <option value="cart_recovery">🛒 Paliktas krepšelis</option>
                        <option value="new_arrival">✨ Naujienos parduotuvėje</option>
                        <option value="vip_invite">💎 Kvietimas į VIP klubą</option>
                        <option value="loyalty_points">💰 Lojalumo taškų priminimas</option>
                        <option value="referral">🤝 Pakviesk draugą</option>
                        <option value="survey">📝 Trumpa apklausa</option>
                        <option value="summer_sale">☀️ Vasaros išpardavimas</option>
                        <option value="winter_sale">❄️ Žiemos išpardavimas</option>
                    </optgroup>

                    <optgroup label="📅 Šventės ir Progos">
                        <option value="birthday">🎂 Gimtadienio sveikinimas</option>
                        <option value="seasonal_christmas">🎄 Kalėdos</option>
                        <option value="seasonal_easter">🥚 Velykos</option>
                        <option value="seasonal_valentines">💖 Valentino diena</option>
                        <option value="seasonal_halloween">🎃 Helovinas</option>
                        <option value="womens_day">🌷 Moters diena</option>
                        <option value="mens_day">🕶️ Vyro diena</option>
                        <option value="childrens_day">🎈 Vaikų gynimo diena</option>
                        <option value="black_friday">⚫ Black Friday</option>
                        <option value="cyber_monday">💻 Cyber Monday</option>
                        <option value="back_to_school">🎒 Atgal į mokyklą</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <div style="margin-top:20px;">
            <label class="form-label">Laiško tema</label>
            <input type="text" name="subject" id="emailSubject" required placeholder="pvz.: Savaitgalio išpardavimas!" class="form-input">
        </div>

        <div style="margin-top:20px;">
            <label class="form-label">Laiško turinis</label>
            
            <textarea name="message" id="hiddenMessage" style="display:none;"></textarea>

            <div class="simple-editor-wrapper">
                <div class="editor-toolbar">
                    <button type="button" class="editor-btn" onclick="execCmd('bold')" title="Paryškinti"><b>B</b></button>
                    <button type="button" class="editor-btn" onclick="execCmd('italic')" title="Pasviras"><i>I</i></button>
                    <button type="button" class="editor-btn" onclick="execCmd('underline')" title="Pabraukti"><u>U</u></button>
                    <button type="button" class="editor-btn" onclick="execCmd('strikeThrough')" title="Perbraukti"><s>S</s></button>
                    <div style="width:1px; height:20px; background:#e5e7eb; margin:0 5px;"></div>
                    <button type="button" class="editor-btn" onclick="execCmd('justifyLeft')" title="Kairė">⬅️</button>
                    <button type="button" class="editor-btn" onclick="execCmd('justifyCenter')" title="Centras">↔️</button>
                    <div style="width:1px; height:20px; background:#e5e7eb; margin:0 5px;"></div>
                    <button type="button" class="editor-btn" onclick="execCmd('insertUnorderedList')" title="Sąrašas su taškais">• Sąrašas</button>
                    <button type="button" class="editor-btn" onclick="execCmd('insertOrderedList')" title="Numeruotas sąrašas">1. Sąrašas</button>
                    <div style="width:1px; height:20px; background:#e5e7eb; margin:0 5px;"></div>
                    <button type="button" class="editor-btn" onclick="createLink()" title="Įterpti nuorodą">🔗</button>
                    <button type="button" class="editor-btn" onclick="execCmd('unlink')" title="Panaikinti nuorodą">❌🔗</button>
                    <div style="width:1px; height:20px; background:#e5e7eb; margin:0 5px;"></div>
                    <button type="button" class="editor-btn" onclick="execCmd('removeFormat')" title="Išvalyti formatavimą">🧹</button>
                </div>
                
                <div id="editor-visual" contenteditable="true"></div>
            </div>

            <p class="text-muted" style="font-size:13px; margin-top:8px; color:#6b7280; display:flex; align-items:center; gap:6px;">
                <span>💡</span> <b>Patarimas:</b> Jūsų tekstas bus automatiškai įdėtas į naująjį „Cukrinukas“ dizaino šabloną (su logotipu ir rėmeliu).
            </p>
        </div>

        <div style="margin-top:24px; text-align:right;">
            <button type="submit" class="btn" style="background: #2563eb; color: white; padding: 12px 28px; font-weight: 600; border-radius: 12px; border: none; cursor: pointer; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); transition: background 0.2s;">
                Siųsti laišką 🚀
            </button>
        </div>
    </form>
</div>

<script>
// --- Paprasto redaktoriaus funkcijos ---
function execCmd(command) {
    document.execCommand(command, false, null);
    document.getElementById('editor-visual').focus();
}

function createLink() {
    const url = prompt("Įveskite nuorodą (pvz., https://cukrinukas.lt):", "https://");
    if (url) {
        document.execCommand("createLink", false, url);
    }
}

// Prieš siunčiant formą, perkeliam turinį iš DIV į TEXTAREA
function syncContent() {
    const visualContent = document.getElementById('editor-visual').innerHTML;
    document.getElementById('hiddenMessage').value = visualContent;
}

document.getElementById('editor-visual').addEventListener('input', syncContent);

// --- Stiliai naudojami šablonuose (Suderinti su account.php / mailer.php) ---
// Mygtuko stilius: --accent (#2563eb), rounded-12px, shadow
const styleBtn = 'background-color: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 12px; display: inline-block; font-weight: 600; font-size: 15px; margin-top: 16px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); font-family: "Inter", sans-serif;';

// Antraštė: --text-main (#0f172a)
const styleH2 = 'color: #0f172a; font-size: 24px; margin-bottom: 20px; font-weight: 700; letter-spacing: -0.5px;';

// Tekstas: --text-muted (#475467)
const styleP = 'color: #475467; font-size: 16px; line-height: 1.6; margin-bottom: 16px;';

// Akcentas tekste: --accent (#2563eb)
const styleHighlight = 'color: #2563eb; font-weight: bold;';

// Dėžutė kodams: --bg (#f7f7fb), --border (#e4e7ec)
const styleBox = 'background-color: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; margin: 24px 0; text-align: center;';

// Kodo stilius: dashed border su --accent
const styleCode = 'background-color: #ffffff; border: 2px dashed #2563eb; color: #2563eb; font-size: 22px; font-weight: 700; padding: 12px 24px; display: inline-block; border-radius: 8px; margin: 8px 0; letter-spacing: 1px;';

// --- Šablonų logika (25 vnt.) ---
const templates = {
    // 1. WELCOME
    welcome: {
        subject: "Sveiki atvykę į Cukrinukas.lt šeimą! 👋",
        body: `<h2 style="${styleH2}">Sveiki atvykę!</h2>
<p style="${styleP}">Džiaugiamės, kad prisijungėte prie smaližių bendruomenės. Nuo šiol pirmieji sužinosite apie naujausius skanėstus ir geriausius pasiūlymus.</p>
<p style="${styleP}">Norėdami padaryti pradžią dar saldesnę, dovanojame Jums nuolaidą pirmajam apsipirkimui:</p>
<div style="${styleBox}">
    <span style="${styleCode}">SVEIKAS10</span>
    <p style="margin-top:12px; font-size:14px; color:#64748b;">Nuolaidos kodas suteikia -10% visam krepšeliui.</p>
</div>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/products" style="${styleBtn}">Pradėti apsipirkimą</a>
</div>`
    },
    
    // 2. ORDER SHIPPED
    order_shipped: {
        subject: "Jūsų užsakymas jau pakeliui! 🚚",
        body: `<h2 style="${styleH2}">Geros naujienos!</h2>
<p style="${styleP}">Jūsų užsakymas buvo kruopščiai supakuotas ir perduotas kurjeriui. Jau visai netrukus galėsite mėgautis savo skanėstais.</p>
<div style="${styleBox}">
    <p style="${styleP}; margin-bottom:0;">Siunta Jus pasieks per <strong>1-3 darbo dienas</strong>.</p>
</div>
<p style="${styleP}">Tikimės, kad saldumynai Jums patiks!</p>
<p style="${styleP}"><em>Cukrinukas komanda</em></p>`
    },

    // 3. PROMO (SALE)
    promo: {
        subject: "Saldus išpardavimas: -20% viskam! 🍭",
        body: `<h2 style="${styleH2}">Metas pasilepinti!</h2>
<p style="${styleP}">Tik šią savaitę <b>Cukrinukas.lt</b> parduotuvėje skelbiame visuotinį išpardavimą. Visiems saldumynams taikome <span style="${styleHighlight}">20% nuolaidą</span>.</p>
<p style="${styleP}">Nuolaidos kodas:</p>
<div style="${styleBox}">
    <span style="${styleCode}">SALDU20</span>
</div>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Griebti nuolaidą</a>
</div>
<p style="font-size:13px; color:#94a3b8; text-align:center; margin-top:24px;">Pasiūlymas galioja iki sekmadienio vidurnakčio.</p>`
    },

    // 4. CART RECOVERY
    cart_recovery: {
        subject: "Jūsų krepšelis liūdi be Jūsų... 🛒",
        body: `<h2 style="${styleH2}">Ar kažką pamiršote?</h2>
<p style="${styleP}">Pastebėjome, kad įsidėjote prekių į krepšelį, bet užsakymo nebaigėte. Jūsų skanėstai vis dar laukia rezervuoti!</p>
<p style="${styleP}">Grįžkite ir užbaikite užsakymą dabar – tai užtruks tik minutę.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/cart" style="${styleBtn}">Tęsti užsakymą</a>
</div>`
    },

    // 5. BIRTHDAY
    birthday: {
        subject: "Su gimtadieniu! 🎂 Dovana Jums",
        body: `<div style="text-align: center;">
<h2 style="${styleH2}">Sveikiname su gimtadieniu! 🥳</h2>
<p style="${styleP}">Šia ypatinga proga norime Jums padovanoti nedidelę staigmeną – <strong>nemokamą pristatymą</strong> kitam Jūsų užsakymui.</p>
<div style="${styleBox}">
    <span style="${styleCode}">GIMTADIENIS</span>
</div>
<p style="${styleP}">Linkime saldžių ir džiugių metų!</p>
<a href="https://cukrinukas.lt" style="${styleBtn}">Atsiimti dovaną</a>
</div>`
    },

    // 6. FEEDBACK
    feedback: {
        subject: "Kaip mums sekėsi? ⭐",
        body: `<h2 style="${styleH2}">Jūsų nuomonė mums svarbi</h2>
<p style="${styleP}">Neseniai pirkote iš Cukrinukas.lt. Ar esate patenkinti prekėmis ir aptarnavimu?</p>
<p style="${styleP}">Būsime labai dėkingi, jei skirsite minutę ir paliksite atsiliepimą.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/account" style="${styleBtn}">Palikti atsiliepimą</a>
</div>`
    },

    // 7. APOLOGY
    apology: {
        subject: "Atsiprašome dėl vėlavimo 😔",
        body: `<h2 style="${styleH2}">Atsiprašome...</h2>
<p style="${styleP}">Norime nuoširdžiai atsiprašyti, kad Jūsų užsakymo vykdymas užtruko ilgiau nei planuota. Mes labai vertiname Jūsų laiką.</p>
<p style="${styleP}">Kaip kompensaciją, prie kito užsakymo pridėsime nedidelę dovanėlę arba taikysime nuolaidą:</p>
<div style="${styleBox}">
    <span style="${styleCode}">ATSIPRASOME15</span>
</div>
<p style="${styleP}">Ačiū už Jūsų kantrybę ir supratingumą.</p>`
    },

    // 8. NEW ARRIVAL
    new_arrival: {
        subject: "Naujienos! Paragaukite pirmieji ✨",
        body: `<h2 style="${styleH2}">Ką tik atvyko!</h2>
<p style="${styleP}">Mūsų lentynas pasiekė visiškai nauji, dar neragauti skoniai! Nuo egzotiškų guminukų iki išskirtinio šokolado.</p>
<p style="${styleP}">Būkite pirmieji, kurie išbandys šias naujienas.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/products?sort=newest" style="${styleBtn}">Žiūrėti naujienas</a>
</div>`
    },

    // 9. RESTOCK
    restock: {
        subject: "Jūsų laukta prekė vėl prekyboje! 🔄",
        body: `<h2 style="${styleH2}">Jos sugrįžo!</h2>
<p style="${styleP}">Turime gerų žinių – prekė, kurios ieškojote, vėl mūsų sandėlyje. Tačiau paskubėkite, kiekis ribotas!</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/products" style="${styleBtn}">Pirkti dabar</a>
</div>`
    },

    // 10. VIP INVITE
    vip_invite: {
        subject: "Jūs tapote VIP klientu! 💎",
        body: `<h2 style="${styleH2}">Sveikiname prisijungus prie elito!</h2>
<p style="${styleP}">Dėl savo lojalumo Jūs patekote į mūsų VIP klientų sąrašą. Tai reiškia išskirtinius pasiūlymus, slaptus išpardavimus ir pirmenybę aptarnavimui.</p>
<p style="${styleP}">Ačiū, kad esate su mumis!</p>`
    },

    // 11. CHRISTMAS
    seasonal_christmas: {
        subject: "Jaukių ir saldžių Šv. Kalėdų! 🎄",
        body: `<div style="text-align: center;">
<h2 style="${styleH2}">Linksmų Šv. Kalėdų!</h2>
<p style="${styleP}">Tegul šios šventės būna pripildytos juoko, šilumos ir, žinoma, saldžių akimirkų.</p>
<p style="${styleP}">Dėkojame, kad šiais metais buvote kartu. Siunčiame Jums šventinę dovaną – nuolaidą:</p>
<div style="${styleBox}">
    <span style="${styleCode}">KALEDOS2024</span>
</div>
<a href="https://cukrinukas.lt" style="${styleBtn}">Apsilankyti parduotuvėje</a>
</div>`
    },

    // 12. EASTER
    seasonal_easter: {
        subject: "Su Šv. Velykomis! 🐣",
        body: `<h2 style="${styleH2}">Pavasariški sveikinimai!</h2>
<p style="${styleP}">Sveikiname Jus su atgimimo švente! Tegul margučių ridenimas būna linksmas, o stalas – gausus skanėstų.</p>
<p style="${styleP}">Velykų proga visiems šokoladiniams kiaušiniams taikome nuolaidą!</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Velykiniai pasiūlymai</a>
</div>`
    },

    // 13. HALLOWEEN
    seasonal_halloween: {
        subject: "Pokštas ar saldainis? 🎃",
        body: `<h2 style="${styleH2}">Šiurpiausiai saldi naktis!</h2>
<p style="${styleP}">Helovinas jau čia! Pasiruoškite gąsdinti ir vaišinti. Tik šiandien – „baisiai“ geros kainos visiems saldainiams.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}" onclick="return false;">Noriu saldainių!</a>
</div>`
    },

    // 14. VALENTINES
    seasonal_valentines: {
        subject: "Meilė tvyro ore... 💖",
        body: `<h2 style="${styleH2}">Saldūs linkėjimai Valentino proga!</h2>
<p style="${styleP}">Nustebinkite savo mylimą žmogų (arba palepinkite save) saldžia dovana. Meilė yra saldi, kaip ir mūsų šokoladas.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Dovanos mylimiesiems</a>
</div>`
    },

    // 15. BLACK FRIDAY (Išimtis - juoda spalva, bet suapvalinimai lieka)
    black_friday: {
        subject: "⚫ BLACK FRIDAY prasideda dabar!",
        body: `<h2 style="${styleH2}; color:#000;">DIDŽIAUSIAS METŲ IŠPARDAVIMAS</h2>
<p style="${styleP}">Tai, ko laukėte visus metus. Nuolaidos net iki <span style="color:#ef4444; font-weight:bold;">-50%</span>!</p>
<p style="${styleP}">Prekių kiekis ribotas, tad nelaukite.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}; background-color:#000000; box-shadow:0 4px 10px rgba(0,0,0,0.3);">PIRKTI DABAR</a>
</div>`
    },

    // 16. CYBER MONDAY
    cyber_monday: {
        subject: "💻 Cyber Monday: paskutinė proga!",
        body: `<h2 style="${styleH2}">Paskutinės išpardavimo valandos</h2>
<p style="${styleP}">Jei nespėjote per Black Friday, Cyber Monday suteikia antrą šansą. Nemokamas pristatymas visiems užsakymams šiandien!</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Paskutinis šansas</a>
</div>`
    },

    // 17. WOMENS DAY
    womens_day: {
        subject: "Su Kovo 8-ąja! 🌷",
        body: `<h2 style="${styleH2}">Žavingosios moterys,</h2>
<p style="${styleP}">Sveikiname Jus su Tarptautine moters diena! Linkime, kad kasdienybė būtų kupina spalvų, šypsenų ir saldžių akimirkų.</p>
<p style="${styleP}">Šia proga dovanojame gėles... ir nuolaidą:</p>
<div style="${styleBox}">
    <span style="${styleCode}">MOTERIMS10</span>
</div>`
    },

    // 18. MENS DAY
    mens_day: {
        subject: "Sveikinimai Vyro dienos proga! 🕶️",
        body: `<h2 style="${styleH2}">Stiprybės ir energijos!</h2>
<p style="${styleP}">Sveikiname su Tarptautine vyro diena. Pasikraukite energijos su mūsų baltyminiais batonėliais ar juoduoju šokoladu.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Vyriškas pasirinkimas</a>
</div>`
    },

    // 19. CHILDRENS DAY
    childrens_day: {
        subject: "Vaikų gynimo diena – laikas dūkti! 🎈",
        body: `<h2 style="${styleH2}">Vaikystė turi būti saldi!</h2>
<p style="${styleP}">Sveikiname visus mažuosius smaližius. Šiandien guminukams ir ledinukams taikome specialias kainas.</p>
<p style="${styleP}">Tegul šypsenos niekada nedingsta nuo vaikų veidų.</p>`
    },

    // 20. BACK TO SCHOOL
    back_to_school: {
        subject: "Atgal į mokyklą su energija! 🎒",
        body: `<h2 style="${styleH2}">Pasiruošę mokslo metams?</h2>
<p style="${styleP}">Kad mokslai eitųsi sklandžiau, reikia pasirūpinti užkandžiais pertraukoms! Kuprinę jau turite, o skanėstais pasirūpinsime mes.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Mokyklinis krepšelis</a>
</div>`
    },

    // 21. SUMMER SALE
    summer_sale: {
        subject: "Karštas vasaros išpardavimas! ☀️",
        body: `<h2 style="${styleH2}">Vasara, saulė ir... nuolaidos!</h2>
<p style="${styleP}">Atsigaivinkite geriausiais pasiūlymais. Vasaros prekių likučių išpardavimas jau prasidėjo.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Nerti į vasarą</a>
</div>`
    },

    // 22. WINTER SALE
    winter_sale: {
        subject: "Žiemos išpardavimas – jaukūs vakarai ❄️",
        body: `<h2 style="${styleH2}">Sušilkite su mūsų pasiūlymais</h2>
<p style="${styleP}">Ilgi žiemos vakarai geriausi su puodeliu karšto šokolado. Pasinaudokite žiemos nuolaidomis!</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt" style="${styleBtn}">Žiemos jaukumas</a>
</div>`
    },

    // 23. REFERRAL
    referral: {
        subject: "Pakviesk draugą ir gauk dovanų! 🤝",
        body: `<h2 style="${styleH2}">Dalintis gera!</h2>
<p style="${styleP}">Ar žinojote, kad pakvietę draugą apsipirkti Cukrinukas.lt, abu gausite po 5€ nuolaidą?</p>
<p style="${styleP}">Nusiųskite savo nuorodą draugui ir mėgaukitės saldumynais pigiau.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/account" style="${styleBtn}">Gauti nuorodą</a>
</div>`
    },

    // 24. SURVEY
    survey: {
        subject: "Padėkite mums tobulėti 📝",
        body: `<h2 style="${styleH2}">Mums trūksta Jūsų nuomonės</h2>
<p style="${styleP}">Norime tapti geriausia saldumynų parduotuve Lietuvoje, bet be Jūsų pagalbos to nepadarysime. Atsakykite į 3 klausimus ir gaukite staigmeną.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/survey" style="${styleBtn}">Dalyvauti apklausoje</a>
</div>`
    },

    // 25. LOYALTY POINTS
    loyalty_points: {
        subject: "Jūs turite nepanaudotų taškų! 💰",
        body: `<h2 style="${styleH2}">Neiššvaistykite savo taškų</h2>
<p style="${styleP}">Primename, kad savo sąskaitoje turite sukaupę lojalumo taškų, kuriuos galite panaudoti kaip nuolaidą kitam apsipirkimui.</p>
<p style="${styleP}">Pažiūrėkite savo likutį prisijungę prie paskyros.</p>
<div style="text-align: center;">
    <a href="https://cukrinukas.lt/account" style="${styleBtn}">Mano taškai</a>
</div>`
    }
};

document.getElementById('templateSelector').addEventListener('change', function() {
    const key = this.value;
    if (templates[key]) {
        // Nustatome temą
        document.getElementById('emailSubject').value = templates[key].subject;
        
        // Įdedame HTML į vizualų redaktorių
        document.getElementById('editor-visual').innerHTML = templates[key].body;
        
        // Atnaujiname paslėptą lauką
        syncContent();
    }
});
</script>
