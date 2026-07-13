<?php
// backend/seed_database.php
require_once '../db.php';

echo "<h2>Starting Database Clean and Seed...</h2>";

try {
    $pdo->beginTransaction();

    // 1. Wipe existing data securely
    echo "Wiping existing data...<br>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE post_comments;");
    $pdo->exec("TRUNCATE TABLE post_likes;");
    $pdo->exec("TRUNCATE TABLE posts;");
    $pdo->exec("TRUNCATE TABLE project_poll_votes;");
    $pdo->exec("TRUNCATE TABLE project_poll_options;");
    $pdo->exec("TRUNCATE TABLE project_polls;");
    $pdo->exec("TRUNCATE TABLE project_messages;");
    $pdo->exec("TRUNCATE TABLE project_files;");
    $pdo->exec("TRUNCATE TABLE project_members;");
    $pdo->exec("TRUNCATE TABLE projects;");
    $pdo->exec("TRUNCATE TABLE connections;");
    $pdo->exec("TRUNCATE TABLE messages;");
    $pdo->exec("TRUNCATE TABLE notifications;");
    $pdo->exec("TRUNCATE TABLE users;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Define our 5 random users
    $users = [
        [
            'first_name' => 'Alex',
            'last_name' => 'Chen',
            'email' => 'alex@test.com',
            'password' => 'password123',
            'skills' => json_encode(['React', 'Node.js', 'JavaScript']),
            'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop',
            'bio' => 'Full-stack developer passionate about building scalable web applications.',
            'post' => [
                'content' => "Just launched my new React portfolio! 🚀 It's been a long journey but I'm really happy with how the animations turned out. Always learning!",
                'image_url' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=600&h=400&fit=crop'
            ]
        ],
        [
            'first_name' => 'Sarah',
            'last_name' => 'Jenkins',
            'email' => 'sarah@test.com',
            'password' => 'password123',
            'skills' => json_encode(['UI/UX Design', 'Figma', 'CSS']),
            'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop',
            'bio' => 'UI/UX Designer focusing on clean, user-centric experiences.',
            'post' => [
                'content' => "Design isn't just what it looks like and feels like. Design is how it works. Spent the day wireframing a new e-commerce concept. 🎨✨",
                'image_url' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=600&h=400&fit=crop'
            ]
        ],
        [
            'first_name' => 'Marcus',
            'last_name' => 'Johnson',
            'email' => 'marcus@test.com',
            'password' => 'password123',
            'skills' => json_encode(['Python', 'Machine Learning', 'Data Analysis']),
            'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop',
            'bio' => 'Data Scientist by day, open-source contributor by night.',
            'post' => [
                'content' => "Finally cracked the accuracy issue on my TensorFlow model! 🧠📈 Data normalization is the key to everything.",
                'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&h=400&fit=crop'
            ]
        ],
        [
            'first_name' => 'Elena',
            'last_name' => 'Rodriguez',
            'email' => 'elena@test.com',
            'password' => 'password123',
            'skills' => json_encode(['Digital Marketing', 'SEO', 'Content Strategy']),
            'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&h=200&fit=crop',
            'bio' => 'Helping brands grow their digital footprint.',
            'post' => [
                'content' => "Just published my Q3 strategy guide for organic growth. Remember, consistency beats intensity when it comes to SEO! 🚀📊",
                'image_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&h=400&fit=crop'
            ]
        ],
        [
            'first_name' => 'David',
            'last_name' => 'Kim',
            'email' => 'david@test.com',
            'password' => 'password123',
            'skills' => json_encode(['Java', 'Spring Boot', 'AWS']),
            'avatar_url' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=200&h=200&fit=crop',
            'bio' => 'Backend engineer who loves cloud architecture and microservices.',
            'post' => [
                'content' => "Migrating a monolith to microservices is no joke, but seeing the deployment times drop from 30 mins to 3 mins is incredibly satisfying. ☁️💻",
                'image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&h=400&fit=crop'
            ]
        ]
    ];

    $stmtUser = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, skills, avatar_url, bio) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtPost = $pdo->prepare("INSERT INTO posts (user_id, content, image_url) VALUES (?, ?, ?)");

    echo "Inserting users and their posts...<br>";

    foreach ($users as $user) {
        $hash = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmtUser->execute([
            $user['first_name'], 
            $user['last_name'], 
            $user['email'], 
            $hash, 
            $user['skills'], 
            $user['avatar_url'], 
            $user['bio']
        ]);
        
        $user_id = $pdo->lastInsertId();

        $stmtPost->execute([
            $user_id,
            $user['post']['content'],
            $user['post']['image_url']
        ]);
        
        echo "✅ Added user: {$user['first_name']} {$user['last_name']} ({$user['email']}) with 1 post.<br>";
    }

    $pdo->commit();
    echo "<h3>🎉 Database wiped and successfully seeded with 5 test users!</h3>";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "⚠️ Error: " . $e->getMessage() . "<br>";
}
?>
