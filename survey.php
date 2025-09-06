<?php
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
            'accuracy_q1' => '1. The LGU AI InfoHub provides accurate answers to my inquiries about local government services.',
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
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .form-container { padding: 0; }
        fieldset {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        legend {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            padding: 0 10px;
        }
        .question {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .question:last-child { border-bottom: none; }
        .question p { margin: 0 0 10px 0; font-weight: 500; }
        .rating-group { display: flex; justify-content: space-between; align-items: center; max-width: 300px; }
        .rating-group label { display: flex; flex-direction: column; align-items: center; cursor: pointer; }
        .rating-group input { margin-bottom: 5px; accent-color: #007bff; }
        .rating-labels { display: flex; justify-content: space-between; max-width: 300px; font-size: 0.9em; color: #555; padding: 0 5px; }
        textarea { width: 100%; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Give Us Your Feedback</h1>
        <p>
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
                        <legend><?php echo htmlspecialchars($section['title']); ?></legend>
                        <?php foreach ($section['questions'] as $key => $question): ?>
                            <div class="question">
                                <p><?php echo htmlspecialchars($question); ?></p>
                                <div class="rating-group">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label>
                                            <input type="radio" name="<?php echo $key; ?>" value="<?php echo $i; ?>" required>
                                            <?php echo $i; ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-labels">
                                    <span>Strongly Disagree</span>
                                    <span>Strongly Agree</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>

                <label for="comment">Additional Comments & Suggestions (Optional)</label>
                <textarea name="comment" id="comment" rows="5" placeholder="Tell us what you think..."></textarea>

                <div class="form-actions">
                    <button type="submit" class="btn backup">Submit Feedback</button>
                    <a href="main.php" class="btn cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>