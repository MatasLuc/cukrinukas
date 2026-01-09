<?php
// admin/emails.php

// Paimame vartotojų sąrašą
$stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();
?>

<style>
    /* Paprasto redaktoriaus stilius */
    .simple-editor-wrapper {
        border: 1px solid #ccc;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }
    .editor-toolbar {
        background: #f3f4f6;
        border-bottom: 1px solid #ccc;
        padding: 8px;
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    .editor-btn {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        cursor: pointer;
        padding: 5px 10px;
        font-size: 14px;
        font-weight: 600;
        min-width: 30px;
    }
    .editor-btn:hover {
        background: #e5e7eb;
    }
    #editor-visual {
        min-height: 300px;
        padding: 16px;
        outline: none;
        overflow-y: auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 14px;
        line-height: 1.5;
    }
    #editor-visual:focus {
        background-color: #fafafa;
    }
    #editor-visual blockquote {
        border-left: 3px solid #ccc;
        margin-left: 0;
        padding-left: 10px;
        color: #666;
    }
</style>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h3>📧 Siųsti laišką</h3>
    </div>

    <form action="admin.php?view=emails" method="POST" class="table-form" onsubmit="syncContent(); return confirm('Ar tikrai norite siųsti šį laišką?');">
        <?php echo csrfField(); ?>
        
        <input type="hidden" name="action" value="send_email">
        
        <div class="grid grid-2">
            <div>
                <label style="display:block; margin-bottom:8px; font-weight:600;">Gavėjas</label>
                <select name="recipient_id" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd; background-color: #fff;">
                    <option value="">-- Pasirinkite gavėją --</option>
                    
                    <option value="all" style="font-weight:bold; color:var(--primary);">📢 SIŲSTI VISIEMS KLIENTAMS (<?php echo count($users); ?>)</option>
                    <option disabled>--------------------------------</option>
                    
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block; margin-bottom:8px; font-weight:600;">Šablonas (greitas užpildymas)</label>
                <select id="templateSelector" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd; background:#f9fafb;">
                    <option value="">-- Pasirinkite šabloną --</option>
                    <option value="promo">🎉 Reklaminis pasiūlymas</option>
                    <option value="order_shipped">📦 Užsakymas išsiųstas</option>
                    <option value="birthday">🎂 Gimtadienio sveikinimas</option>
                    <option value="feedback">⭐ Atsiliepimo prašymas</option>
                    <option value="apology">😔 Atsiprašymas dėl vėlavimo</option>
                </select>
            </div>
        </div>

        <div style="margin-top:16px;">
            <label style="display:block; margin-bottom:8px; font-weight:600;">Laiško tema</label>
            <input type="text" name="subject" id="emailSubject" required placeholder="pvz.: Savaitgalio išpardavimas!" style="width:100%;">
        </div>

        <div style="margin-top:16px;">
            <label style="display:block; margin-bottom:8px; font-weight:600;">Laiško turinis</label>
            
            <textarea name="message" id="hiddenMessage" style="display:none;"></textarea>

            <div class="simple-editor-wrapper">
                <div class="editor-toolbar">
                    <button type="button" class="editor-btn" onclick="execCmd('bold')" title="Paryškinti"><b>B</b></button>
                    <button type="button" class="editor-btn" onclick="execCmd('italic')" title="Pasviras"><i>I</i></button>
                    <button type="button" class="editor-btn" onclick="execCmd('underline')" title="Pabraukti"><u>U</u></button>
                    <div style="width:1px; background:#ccc; margin:0 5px;"></div>
                    <button type="button" class="editor-btn" onclick="execCmd('insertUnorderedList')" title="Sąrašas su taškais">• Sąrašas</button>
                    <button type="button" class="editor-btn" onclick="execCmd('insertOrderedList')" title="Numeruotas sąrašas">1. Sąrašas</button>
                    <div style="width:1px; background:#ccc; margin:0 5px;"></div>
                    <button type="button" class="editor-btn" onclick="createLink()" title="Įterpti nuorodą">🔗</button>
                    <button type="button" class="editor-btn" onclick="execCmd('unlink')" title="Panaikinti nuorodą">❌🔗</button>
                    <div style="width:1px; background:#ccc; margin:0 5px;"></div>
                    <button type="button" class="editor-btn" onclick="execCmd('removeFormat')" title="Išvalyti formatavimą">Išvalyti</button>
                </div>
                
                <div id="editor-visual" contenteditable="true"></div>
            </div>

            <p class="text-muted" style="font-size:12px; margin-top:4px;">
                Jūsų tekstas bus automatiškai įdėtas į standartinį „Cukrinukas“ dizaino rėmelį su logotipu.
            </p>
        </div>

        <div style="margin-top:24px; text-align:right;">
            <button type="submit" class="btn" style="background:var(--primary); color:white; padding: 12px 24px;">
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

// Sinchronizuojame ir rašymo metu, kad netyčia neprarastume
document.getElementById('editor-visual').addEventListener('input', syncContent);

// --- Šablonų logika ---
const templates = {
    promo: {
        subject: "Specialus pasiūlymas tik Jums! 🍭",
        body: `<p>Sveiki!</p>
<p>Norime pranešti, kad šią savaitę <b>Cukrinukas.lt</b> parduotuvėje vyksta ypatinga akcija.</p>
<p>Pasinaudokite proga įsigyti savo mėgstamiausių saldumynų su <strong style="color: #e03e2d;">20% nuolaida</strong>! Tiesiog atsiskaitymo metu naudokite kodą:</p>
<h3 style="text-align: center; background-color: #fffacd; padding: 10px;">SALDU20</h3>
<p>Pasiūlymas galioja iki sekmadienio.</p>
<p>Laukiame Jūsų sugrįžtant!</p>`
    },
    order_shipped: {
        subject: "Jūsų užsakymas jau pakeliui! 🚚",
        body: `<p>Sveiki,</p>
<p>Turime puikių žinių! Jūsų užsakymas buvo sėkmingai supakuotas ir perduotas kurjeriui.</p>
<p>Siuntą turėtumėte gauti per <strong>1-3 darbo dienas</strong>.</p>
<hr />
<p>Tikimės, kad saldumynai Jums patiks!</p>
<p><em>Cukrinukas komanda</em></p>`
    },
    birthday: {
        subject: "Su gimtadieniu! 🎂 Dovana Jums",
        body: `<div style="text-align: center;">
<h2>Sveikiname su gimtadieniu! 🥳</h2>
<p>Šia ypatinga proga norime Jums padovanoti nedidelę staigmeną – <strong>nemokamą pristatymą</strong> kitam Jūsų užsakymui.</p>
<p>Linkime saldžių ir džiugių metų!</p>
</div>`
    },
    feedback: {
        subject: "Kaip mums sekėsi? ⭐",
        body: `<p>Sveiki,</p>
<p>Neseniai pirkote iš Cukrinukas.lt. Mums labai svarbi Jūsų nuomonė!</p>
<p>Ar esate patenkinti prekėmis? Būsime labai dėkingi, jei rasite minutėlę ir brūkštelėsite atsakymą arba paliksite įvertinimą mūsų puslapyje.</p>
<p>Ačiū, kad padedate mums tobulėti!</p>`
    },
    apology: {
        subject: "Atsiprašome dėl vėlavimo 😔",
        body: `<p>Sveiki,</p>
<p>Norime nuoširdžiai atsiprašyti, kad Jūsų užsakymo vykdymas užtruko ilgiau nei planuota.</p>
<p>Dedame visas pastangas, kad siunta Jus pasiektų kuo greičiau. Kaip kompensaciją, prie kito užsakymo pridėsime nedidelę dovanėlę.</p>
<p>Ačiū už Jūsų kantrybę ir supratingumą.</p>`
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
