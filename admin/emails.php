<?php
// admin/emails.php

// Paimame vartotojų sąrašą
$stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();
?>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: '#emailMessage',
    height: 500,
    plugins: [
      'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
      'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
      'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
    ],
    toolbar: 'undo redo | formatselect | ' +
    'bold italic backcolor | alignleft aligncenter ' +
    'alignright alignjustify | bullist numlist outdent indent | ' +
    'removeformat | emoticons | help',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
    language: 'lt' // Jei reikia lietuvių k., bet veiks ir EN
  });
</script>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h3>📧 Siųsti laišką</h3>
    </div>

    <form action="admin.php?view=emails" method="POST" class="table-form" onsubmit="return confirm('Ar tikrai norite siųsti šį laišką?');">
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
            <textarea name="message" id="emailMessage" placeholder="Rašykite savo laišką čia..."></textarea>
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
const templates = {
    promo: {
        subject: "Specialus pasiūlymas tik Jums! 🍭",
        body: `<p>Sveiki!</p>
<p>Norime pranešti, kad šią savaitę <b>Cukrinukas.lt</b> parduotuvėje vyksta ypatinga akcija.</p>
<p>Pasinaudokite proga įsigyti savo mėgstamiausių saldumynų su <span style="color: #e03e2d;"><strong>20% nuolaida</strong></span>! Tiesiog atsiskaitymo metu naudokite kodą:</p>
<h2 style="text-align: center;"><span style="background-color: #f1c40f; padding: 5px 15px; border-radius: 5px;">SALDU20</span></h2>
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

// JavaScript atnaujintas, kad veiktų su TinyMCE
document.getElementById('templateSelector').addEventListener('change', function() {
    const key = this.value;
    if (templates[key]) {
        document.getElementById('emailSubject').value = templates[key].subject;
        // Naudojame TinyMCE API turiniui nustatyti
        if (tinymce.get('emailMessage')) {
            tinymce.get('emailMessage').setContent(templates[key].body);
        } else {
            // Fallback, jei netyčia redaktorius dar neužsikrovė
            document.getElementById('emailMessage').value = templates[key].body;
        }
    }
});
</script>
