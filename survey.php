<?php
ob_start();
$customSessionPath = __DIR__ . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($customSessionPath)) { @mkdir($customSessionPath, 0777, true); }
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_save_path($customSessionPath);
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_class = '';

$sections = [
    'A' => [
        'title' => 'Section A: Accuracy (System Performance & Reliability)',
        'questions' => [
            'accuracy_q1' => '1. The LGU AI InfoChat provides accurate answers to my inquiries about local government services.',
            'accuracy_q2' => '2. The chatbot correctly identifies and responds to questions about documentation requirements.',
            'accuracy_q3' => '3. The system demonstrates reliable understanding of the context of my questions.',
            'accuracy_q4' => '4. The responses from the chatbot are consistent and trustworthy.',
            'accuracy_q5' => '5. The use of natural language processing (NLP) improves the quality and accuracy of responses.',
        ]
    ],
    'B' => [
        'title' => 'Section B: User-Friendliness (Ease of Use & Accessibility)',
        'questions' => [
            'user_friendliness_q1' => '1. The chatbot is easy to use, even for those with little experience in technology.',
            'user_friendliness_q2' => '2. The interface is simple and intuitive to navigate.',
            'user_friendliness_q3' => '3. The voice-activated feature makes the chatbot more accessible for users.',
            'user_friendliness_q4' => '4. The system communicates in a natural, human-like conversational style.',
            'user_friendliness_q5' => '5. Using the chatbot makes accessing LGU information faster and more convenient compared to traditional methods.',
        ]
    ],
    'C' => [
        'title' => 'Section C: Overall Satisfaction & Impact',
        'questions' => [
            'satisfaction_q1' => '1. The LGU AI InfoHub helps me save time in getting the information I need.',
            'satisfaction_q2' => '2. The system improves my access to local government services.',
            'satisfaction_q3' => '3. I am satisfied with the overall performance of the chatbot.',
            'satisfaction_q4' => '4. I would recommend this chatbot for use by other citizens.',
        ]
    ]
];

$question_keys = [];
foreach ($sections as $section) {
    $question_keys = array_merge($question_keys, array_keys($section['questions']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_stmt = $conn->prepare("SELECT id FROM surveys WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "You have already submitted your feedback. Thank you!";
        $message_class = 'success';
    } else {
        $ratings = [];
        $all_valid = true;
        foreach ($question_keys as $key) {
            $rating = $_POST[$key] ?? null;
            if (!$rating || !in_array($rating, [1, 2, 3, 4, 5])) {
                $all_valid = false;
                break;
            }
            $ratings[] = (int)$rating;
        }

        $comment = trim($_POST['comment'] ?? '');

        if (!$all_valid) {
            $message = "Please answer all questions with a rating from 1 to 5.";
            $message_class = 'error';
        } else {
            $columns = implode(', ', $question_keys);
            $placeholders = implode(', ', array_fill(0, count($question_keys), '?'));
            $types = str_repeat('i', count($question_keys));

            $sql = "INSERT INTO surveys (user_id, $columns, comment) VALUES (?, $placeholders, ?)";
            $stmt = $conn->prepare($sql);

            $params = array_merge([$user_id], $ratings, [$comment]);
            $stmt->bind_param("i" . $types . "s", ...$params);
            
            if ($stmt->execute()) {
                $_SESSION['survey_status'] = 'success';
                header("Location: main.php");
                exit();
            } else {
                $message = "An error occurred while submitting your feedback. Please try again.";
                $message_class = 'error';
            }
            $stmt->close();
        }
    }
    $check_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Survey</title>
    <link rel="icon" type="image/png" href="assets/roxas_seal.png">
    <link rel="stylesheet" href="main_style.css?v=<?php echo filemtime('main_style.css'); ?>">
    <style>
        body { font-family: "Montserrat", sans-serif; text-rendering: optimizeLegibility; background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92)), url('assets/bg1.jpg') center / cover no-repeat }
        h1 { margin: 0 0 .8rem 0; text-align: center; letter-spacing: .2px; }
        p { color: #555; line-height: 1.6; }
        .status-message { margin: 1rem 0; padding: 12px 14px; border-radius: 8px; }
        .status-message.success { background: #eaf7ea; border: 1px solid #cbe9cb; color: #246b24; }
        .status-message.error { background: #fff2f2; border: 1px solid #ffd9d9; color: #8a1f1f; }
    
        .form-container { padding: 0; }
        fieldset {
            border: 1px solid #eee;
            border-radius: 12px;
            background: #fff;
            padding: clamp(18px, 2.2vw, 28px) clamp(16px, 2vw, 28px);
            margin-bottom: clamp(18px, 2vw, 28px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }
        legend {
            font-size: clamp(1.1rem, 2.4vw, 1.6rem);
            font-weight: 800;
            color: #1f2a44;
            padding: 0 8px;
            letter-spacing: .2px;
        }
        .question { margin: 0 0 26px 0; display: grid; justify-items: center; }
        .question p { margin: 0 0 16px 0; font-weight: 700; text-align: center; line-height: 1.7; color: #2a2a2a; max-width: 78ch; font-size: clamp(.98rem, 1.6vw, 1.1rem); }

        /* Circular 1–5 control laid on a track, color animates red (1) to green (5) */
        .rating-group {
            --p: 0; /* percentage fill 0-100 */
            --h: 0; /* hue 0 (red) to 120 (green) */
            position: relative;
            width: min(720px, 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px clamp(18px, 3vw, 28px) 12px; /* inline padding reserves room for endpoints */
            margin: 0 auto;
        }
        .rating-group::before {
            content: "";
            position: absolute;
            left: 28px; right: 28px; top: 50%;
            height: 5px; background: #e6ecf4; border-radius: 999px; transform: translateY(-50%);
            z-index: 0;
        }
        .rating-group::after {
            content: "";
            position: absolute;
            left: 28px; top: 50%; height: 5px; border-radius: 999px; transform: translateY(-50%);
            background: hsl(var(--h), 75%, 50%);
            width: calc((100% - 56px) * (var(--p) / 100));
            transition: width .35s ease, background-color .35s ease;
            z-index: 0;
        }
        .rating-option { display: inline-block; position: relative; z-index: 1; }
        .rating-option input { position: absolute; opacity: 0; pointer-events: none; }
        .rating-option span {
            display: inline-grid; place-items: center;
            width: clamp(30px, 4.4vw, 36px); height: clamp(30px, 4.4vw, 36px); border-radius: 999px;
            border: 2px solid #c8d4ee; background: #fff; color: #7a88a8; font-weight: 700; font-size: .95rem;
            transition: background-color .25s ease, color .25s ease, transform .15s ease, border-color .25s ease, box-shadow .2s ease;
        }
        .rating-option:hover span { transform: scale(1.06); box-shadow: 0 4px 14px rgba(0,0,0,.08); }
        .rating-option input:focus + span { box-shadow: 0 0 0 3px rgba(80,120,255,.25); }
        .rating-option input:checked + span {
            background: hsl(var(--h), 75%, 50%);
            border-color: hsl(var(--h), 75%, 45%);
            color: #fff;
        }

        .rating-labels { display: flex; justify-content: space-between; width: min(680px, 100%); font-size: .95em; color: #616b80; padding: 10px 16px 0 16px; margin: 0 auto; }
        .rating-labels span:first-child { color: hsl(calc(120 - var(--h)), 70%, 40%); font-weight: 600; }
        .rating-labels span:last-child { color: hsl(var(--h), 65%, 35%); font-weight: 700; }

        input[type="text"], textarea { width: 100%; box-sizing: border-box; border-radius: 10px; border: 1px solid #e5e5e5; padding: 10px; font-family: inherit; }
        input[type="text"]:focus, textarea:focus { outline: none; border-color: #246b24; box-shadow: 0 0 0 3px rgba(36,107,36,.15); }

        /* Comment + actions block */
        .comment-block { margin-top: 1rem; border: 1px solid #eef0f2; border-radius: 12px; padding: 14px; background: #fff; }
        .comment-block label { display: block; font-weight: 600; margin-bottom: 8px; }
        .comment-block .actions { display: flex; gap: .6rem; margin-top: 12px; }
        .btn { display: inline-block; padding: .65rem 1.1rem; border-radius: 10px; border: 1px solid transparent; text-decoration: none; font-weight: 600; cursor: pointer; }
        .btn.submit-green { background: #246b24; border-color: #246b24; color: #fff; }
        .btn.submit-green:hover { background: #2d8a2d; border-color: #2d8a2d; }
        .btn.cancel-red { background: #ff6565; border-color: #ff6565; color: #fff; }
        .btn.cancel-red:hover { background: #FF3333; border-color: #FF3333; }

        /* Responsive */
        @media (max-width: 980px) {
            .container { padding: 0 20px; }
            .rating-group { width: 100%; padding: 20px 20px 12px; }
            .rating-labels { width: 100%; padding: 8px 12px 0 12px; }
            .survey-card-inner { transform: translateY(-16px); }
        }
        @media (max-width: 640px) {
            .container { margin: 1rem auto; padding: 0 16px; }
            fieldset { padding: 16px; }
            legend { font-size: 1.1rem; }
            .survey-card-inner { transform: translateY(-10px); border-radius: 12px; }
            .question p { font-size: .98rem; }
            .rating-group { padding: 18px 16px 10px; }
            .rating-option span { width: clamp(28px, 9vw, 34px); height: clamp(28px, 9vw, 34px); }
            .rating-labels { font-size: .88rem; }
            .comment-block { padding: 12px; }
            .comment-block .actions { flex-direction: column; }
            .comment-block .actions .btn { width: 100%; text-align: center; }
        }
        @media (max-width: 420px) {
            .rating-option span { width: 32px; height: 32px; font-size: .9rem; }
            .rating-group::before { left: 22px; right: 22px; }
            .rating-group::after { left: 22px; width: calc((100% - 44px) * (var(--p) / 100)); }
        }
        @media (prefers-reduced-motion: reduce) {
            .rating-option span,
            .rating-group::after,
            .survey-card-inner { transition: none; }
        }

        /* Soft divider used above section headings */
        .section-divider {
            height: 24px;
            border-bottom: 1px solid #e6e6e6;
            background: linear-gradient(to bottom, rgba(0,0,0,.08), rgba(0,0,0,0));
            margin: 0 0 8px 0;
        }
    </style>
</head>
<body>
    <div class="utility-topbar" role="navigation" aria-label="Utility">
        <div class="utility-inner">
            <span class="mayor">Mayor <strong>Benedict C. Calderon</strong></span>
            <div class="utility-actions">
                <div class="lang-menu" id="langMenu">
                    <button class="lang-trigger" id="langTrigger" aria-haspopup="true" aria-expanded="false">
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2c.9 0 1.75.19 2.52.54-.3.38-.58.84-.83 1.36H10.3A7.98 7.98 0 0 1 12 4Zm-2.34 2h4.68c-.2.6-.37 1.26-.49 2H10.15c.12-.74.29-1.4.5-2ZM6.1 8h2.86c-.08.64-.12 1.31-.12 2H5.52c.12-.71.33-1.38.58-2Zm-1 4h3.84c.02.69.07 1.36.15 2H5.52c-.18-.64-.3-1.31-.42-2Zm.94 4h2.97c.24.92.57 1.73.96 2.37A8.03 8.03 0 0 1 6.04 16ZM12 20c-1.26 0-2.42-1.57-3.06-4h6.12c-.64 2.43-1.8 4-3.06 4Zm2.99-1.63c.39-.64.72-1.45.96-2.37h2.97a8.03 8.03 0 0 1-3.93 2.37ZM18.48 14h-3.84c.08-.64.13-1.31.15-2h3.84c-.12.69-.24 1.36-.15 2Zm.15-4h-3.42c0-.69-.04-1.36-.12-2h2.86c.25.62.43 1.29.58 2ZM12 4c1.26 0 2.42 1.57 3.06 4h-6.12C9.58 5.57 10.74 4 12 4Z"/></svg>
                        <span id="currentLangLabel">English</span>
                        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" class="chevron"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg>
                    </button>
                    <ul class="lang-dropdown" id="langDropdown" role="listbox" hidden>
                        <li role="option" data-lang="en">English</li>
                        <li role="option" data-lang="fil">Tagalog (Filipino)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar" role="navigation" aria-label="Primary">
        <div class="container">
            <a href="/" class="logo" style="color: #FF3333;">
                <img src="assets/roxas_seal.png" alt="Logo" class="logo-img"> InfoChat
            </a>
            <div class="nav-links">
                <a href="main.php" class="register-btn">Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <section class="hero-banner">
        <div class="hero-overlay">
            <div class="hero-chip" id="i18n-welcome" data-i18n="welcome">WELCOME TO</div>
            <h1 class="hero-title" id="i18n-title" data-i18n="title">LGU OF ROXAS: INFOCHAT</h1>
        </div>
    </section>

    <section class="survey-card">
        <div class="container">
            <div class="survey-card-inner">
        <div class="section-divider"></div>
        <h1 data-i18n="page_heading">Give Us Your Feedback</h1>
        <p data-i18n="page_intro">
            Your feedback helps us improve InfoHUB. Please indicate your level of agreement with each statement by choosing a rating from 1 (Strongly Disagree) to 5 (Strongly Agree).
        </p>

        <?php if ($message): ?>
            <div class="status-message <?php echo $message_class; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form action="survey.php" method="POST">
                <?php foreach ($sections as $section_key => $section): ?>
                    <fieldset>
                        <legend data-i18n="<?php echo 'section_' . $section_key . '_title'; ?>"><?php echo htmlspecialchars($section['title']); ?></legend>
                        <?php foreach ($section['questions'] as $key => $question): ?>
                            <div class="question">
                                <p data-i18n="<?php echo $key; ?>"><?php echo htmlspecialchars($question); ?></p>
                                <div class="rating-group" role="radiogroup" aria-label="Rate from 1 to 5" data-field>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label class="rating-option">
                                            <input type="radio" name="<?php echo $key; ?>" value="<?php echo $i; ?>" required aria-label="<?php echo $i; ?>">
                                            <span><?php echo $i; ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-labels">
                                    <span data-i18n="strongly_disagree">Strongly Disagree</span>
                                    <span data-i18n="strongly_agree">Strongly Agree</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>

                <div class="comment-block">
                    <label for="comment" data-i18n="comments_label">Additional Comments & Suggestions (Optional)</label>
                    <textarea name="comment" id="comment" rows="5" placeholder="Tell us what you think..." data-i18n-placeholder="comment_placeholder"></textarea>
                    <div class="actions">
                        <button type="submit" class="btn submit-green" data-i18n="submit_feedback">Submit Feedback</button>
                        <a href="main.php" class="btn cancel-red" data-i18n="cancel">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
            </div>
        </div>
    </section>

    <script>
        // Animate track fill and circle hues red(1)->green(5)
        document.querySelectorAll('.rating-group').forEach(group => {
            const inputs = group.querySelectorAll('input[type="radio"]');
            function update() {
                let val = 0;
                inputs.forEach(i => { if (i.checked) val = parseInt(i.value, 10); });
                const percent = (val - 1) / 4 * 100; // 0..100
                const hue = 0 + (val - 1) * (120 / 4); // 0..120
                group.style.setProperty('--p', isNaN(percent)?0:percent);
                group.style.setProperty('--h', isNaN(hue)?0:hue);
            }
            inputs.forEach(i => i.addEventListener('change', update));
            update();
        });

        // Minimal language dropdown to match main page behavior
        (function(){
            const trigger = document.getElementById('langTrigger');
            const dropdown = document.getElementById('langDropdown');
            const label = document.getElementById('currentLangLabel');
            const dict = {
                en: {
                    welcome: 'WELCOME TO',
                    title: 'LGU OF ROXAS: INFOCHAT',
                    page_heading: 'Give Us Your Feedback',
                    page_intro: 'Your feedback helps us improve InfoHUB. Please indicate your level of agreement with each statement by choosing a rating from 1 (Strongly Disagree) to 5 (Strongly Agree).',
                    strongly_disagree: 'Strongly Disagree',
                    strongly_agree: 'Strongly Agree',
                    comments_label: 'Additional Comments & Suggestions (Optional)',
                    comment_placeholder: 'Tell us what you think...',
                    submit_feedback: 'Submit Feedback',
                    cancel: 'Cancel',
                    section_A_title: 'Section A: Accuracy (System Performance & Reliability)',
                    section_B_title: 'Section B: User-Friendliness (Ease of Use & Accessibility)',
                    section_C_title: 'Section C: Overall Satisfaction & Impact',
                    accuracy_q1: '1. The LGU AI InfoHub provides accurate answers to my inquiries about local government services.',
                    accuracy_q2: '2. The chatbot correctly identifies and responds to questions about documentation requirements.',
                    accuracy_q3: '3. The system demonstrates reliable understanding of the context of my questions.',
                    accuracy_q4: '4. The responses from the chatbot are consistent and trustworthy.',
                    accuracy_q5: '5. The use of natural language processing (NLP) improves the quality and accuracy of responses.',
                    user_friendliness_q1: '1. The chatbot is easy to use, even for those with little experience in technology.',
                    user_friendliness_q2: '2. The interface is simple and intuitive to navigate.',
                    user_friendliness_q3: '3. The voice-activated feature makes the chatbot more accessible for users.',
                    user_friendliness_q4: '4. The system communicates in a natural, human-like conversational style.',
                    user_friendliness_q5: '5. Using the chatbot makes accessing LGU information faster and more convenient compared to traditional methods.',
                    satisfaction_q1: '1. The LGU AI InfoHub helps me save time in getting the information I need.',
                    satisfaction_q2: '2. The system improves my access to local government services.',
                    satisfaction_q3: '3. I am satisfied with the overall performance of the chatbot.',
                    satisfaction_q4: '4. I would recommend this chatbot for use by other citizens.'
                },
                fil: {
                    welcome: 'MALIGAYANG PAGDATING SA',
                    title: 'LGU NG ROXAS: INFOCHAT',
                    page_heading: 'Ibahagi ang Iyong Feedback',
                    page_intro: 'Tumutulong ang iyong feedback upang mapabuti ang InfoHUB. Pakipili ang antas ng iyong pagsang-ayon sa bawat pahayag mula 1 (Lubos na Hindi Sumasang-ayon) hanggang 5 (Lubos na Sumasang-ayon).',
                    strongly_disagree: 'Lubos na Hindi Sumasang-ayon',
                    strongly_agree: 'Lubos na Sumasang-ayon',
                    comments_label: 'Karagdagang Komento at Suhestiyon (Opsyonal)',
                    comment_placeholder: 'Ibahagi ang iyong saloobin...',
                    submit_feedback: 'Isumite ang Feedback',
                    cancel: 'Kanselahin',
                    section_A_title: 'Seksyon A: Kawastuhan (Pagganap at Pagkakatiwalaan ng Sistema)',
                    section_B_title: 'Seksyon B: Dali Gamitin (Kadalian at Accessibility)',
                    section_C_title: 'Seksyon C: Kabuuang Kasiyahan at Epekto',
                    accuracy_q1: '1. Nagbibigay ang LGU AI InfoHub ng tumpak na sagot sa aking mga katanungan tungkol sa mga serbisyo ng lokal na pamahalaan.',
                    accuracy_q2: '2. Tama ang pagkilala at pagsagot ng chatbot sa mga tanong tungkol sa mga kinakailangang dokumento.',
                    accuracy_q3: '3. Ipinapakita ng sistema ang maaasahang pag-unawa sa konteksto ng aking mga tanong.',
                    accuracy_q4: '4. Ang mga tugon ng chatbot ay pare-pareho at mapagkakatiwalaan.',
                    accuracy_q5: '5. Pinapabuti ng paggamit ng natural language processing (NLP) ang kalidad at kawastuhan ng mga tugon.',
                    user_friendliness_q1: '1. Madaling gamitin ang chatbot, kahit para sa mga may kaunting karanasan sa teknolohiya.',
                    user_friendliness_q2: '2. Simple at madaling lisanin ang interface.',
                    user_friendliness_q3: '3. Ginagawang mas accessible ng voice-activated na tampok ang chatbot para sa mga gumagamit.',
                    user_friendliness_q4: '4. Nakikipag-usap ang sistema sa natural at parang-taong istilo ng pakikipag-usap.',
                    user_friendliness_q5: '5. Mas mabilis at mas maginhawa ang pagkuha ng impormasyon mula sa LGU sa pamamagitan ng chatbot kumpara sa tradisyonal na paraan.',
                    satisfaction_q1: '1. Tinutulungan akong makatipid ng oras ng LGU AI InfoHub sa pagkuha ng impormasyong kailangan ko.',
                    satisfaction_q2: '2. Pinapahusay ng sistema ang aking pag-access sa mga serbisyo ng lokal na pamahalaan.',
                    satisfaction_q3: '3. Masaya ako sa kabuuang pagganap ng chatbot.',
                    satisfaction_q4: '4. Ire-rekomenda ko ang chatbot na ito para sa ibang mamamayan.'
                }
            };
            function applyLang(lang){
                const strings = dict[lang] || dict.en;
                document.querySelectorAll('[data-i18n]').forEach(el=>{
                    const key = el.getAttribute('data-i18n');
                    if (strings[key]) el.textContent = strings[key];
                });
                document.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
                    const key = el.getAttribute('data-i18n-placeholder');
                    if (strings[key]) el.setAttribute('placeholder', strings[key]);
                });
                localStorage.setItem('lang', lang);
                label.textContent = lang === 'fil' ? 'Tagalog (Filipino)' : 'English';
            }
            if (!trigger || !dropdown) return;
            const toggle = (open)=>{
                const willOpen = typeof open === 'boolean' ? open : dropdown.hasAttribute('hidden');
                if (willOpen) { dropdown.removeAttribute('hidden'); trigger.setAttribute('aria-expanded','true'); }
                else { dropdown.setAttribute('hidden',''); trigger.setAttribute('aria-expanded','false'); }
            }
            trigger.addEventListener('click', ()=> toggle());
            dropdown.querySelectorAll('li[role="option"]').forEach(li=>{
                li.addEventListener('click', ()=>{
                    const lang = li.getAttribute('data-lang');
                    applyLang(lang);
                    toggle(false);
                });
            });
            document.addEventListener('click', (e)=>{ if (!document.getElementById('langMenu').contains(e.target)) toggle(false); });
            // init
            applyLang(localStorage.getItem('lang') || 'en');
        })();
    </script>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="footer-logo"><img src="assets/roxas_seal.png" alt="Footer logo"> InfoChat</div>
                <p>Your gateway to tools, services, and updates.</p>
            </div>
            <div class="footer-cols">
                <div class="footer-col">
                    <h4>Community</h4>
                    <ul>
                        <li><a href="survey.php">Feedback</a></li>
                        <li><a href="#">Support</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Administration</h4>
                    <ul>
                        <li><a href="admin_panel.php">Admin Panel</a></li>
                        <li><a href="logout.php">Log Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="legal-bar">
            <div class="legal-inner">
                <span>© <?php echo date('Y'); ?> InfoHub</span>
                <div class="social">
                    <a href="mailto:info@example.com" aria-label="Email">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2l8 5 8-5"/></svg>
                    </a>
                    <a href="https://www.facebook.com/profile.php?id=61577270600872" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1 .9 0 1.8.1 1.8.1v2h-1c-1 0-1.3.6-1.3 1.2V12h2.3l-.4 3h-1.9v7A10 10 0 0 0 22 12"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>