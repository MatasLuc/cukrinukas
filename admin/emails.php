<?php
// admin/emails.php

// Paimame vartotojų sąrašą
$stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <h3>📧 Siųsti laišką klientui</h3>
    </div>

    <form action="admin.php?view=emails" method="POST" class="table-form">
        <?php echo csrfField(); ?>
        
        <input type="hidden" name="action" value="send_email">
        
        <div class="grid grid-2">
            <div>
                <label style="display:block; margin-bottom:8px; font-weight:600;">Gavėjas</label>
                <select name="recipient_id" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;">
                    <option value="">-- Pasirinkite klientą --</option>
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
            <input type="text" name="subject" id="emailSubject" required placeholder="Įveskite laiško temą..." style="width:100%;">
        </div>

        <div style="margin-top:16px;">
            <label style="display:block; margin-bottom:8px; font-weight:600;">Laiško turinis (HTML)</label>
            <textarea name="message" id="emailMessage" rows="10" required placeholder="Rašykite čia... Galite naudoti HTML žymes kaip <b>paryškinta</b>, <br> nauja eilutė ir pan." style="width:100%; font-family:monospace;"></textarea>
            <p class="text-muted" style="font-size:12px; margin-top:4px;">
                Pastaba: Laiškas bus automatiškai įdėtas į standartinį „Cukrinukas“ dizaino rėmelį.
            </p>
        </div>

        <div style="margin-top:24px; text-align:right;">
            <button type="submit" class="btn" style="background:var(--primary); color:white;">
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
<p>Pasinaudokite proga įsigyti savo mėgstamiausių saldumynų su <strong>20% nuolaida</strong>! Tiesiog atsiskaitymo metu naudokite kodą:</p>
<h3 style="text-align:center; color:#4f46e5;">SALDU20</h3>
<p>Laukiame Jūsų sugrįžtant!</p>`
    },
    order_shipped: {
        subject: "Jūsų užsakymas jau pakeliui! 🚚",
        body: `<p>Sveiki,</p>
<p>Turime puikių žinių! Jūsų užsakymas buvo sėkmingai supakuotas ir perduotas kurjeriui.</p>
<p>Siuntą turėtumėte gauti per 1-3 darbo dienas.</p>
<p>Ačiū, kad perkate pas mus!</p>`
    },
    birthday: {
        subject: "Su gimtadieniu! 🎂 Dovana Jums",
        body: `<p>Sveikiname su gimtadieniu!</p>
<p>Šia ypatinga proga norime Jums padovanoti nedidelę staigmeną – <strong>nemokamą pristatymą</strong> kitam Jūsų užsakymui.</p>
<p>Linkime saldžių ir džiugių metų!</p>`
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
        document.getElementById('emailSubject').value = templates[key].subject;
        document.getElementById('emailMessage').value = templates[key].body;
    }
});
</script>
