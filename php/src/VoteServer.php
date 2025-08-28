<?php
namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class VoteServer implements MessageComponentInterface {
    protected $clients;
    private $pdo;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "WebSocket Server started.\n";
        $this->connectDb();
    }

    private function connectDb() {
        $host = 'db'; $db = 'voting_app'; $user = 'appuser'; $pass = 'apppass';
        $retries = 5;
        while ($retries > 0) {
            try {
                $this->pdo = new \PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
                echo "Database connection successful.\n";
                return;
            } catch (\PDOException $e) {
                $retries--;
                echo "DB connection failed. Retrying...\n";
                if ($retries === 0) die("DB connection error: " . $e->getMessage());
                sleep(3);
            }
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Received message: {$msg}\n";
        $data = json_decode($msg, true);

        // "vote" アクションの場合: user_idを受け取るように変更
        if (isset($data['action']) && $data['action'] === 'vote' && isset($data['choice_id']) && isset($data['poll_id']) && isset($data['user_id'])) {
            $this->handleVote($data['poll_id'], $data['choice_id'], $data['user_id']);
        }
        
        if (isset($data['action']) && $data['action'] === 'request_update' && isset($data['poll_id'])) {
            $this->sendPollData($from, $data['poll_id']);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    // 投票を処理するメソッド: user_idで重複チェック
    private function handleVote($poll_id, $choice_id, $user_id) {
        try {
            // 1. このuser_idが既にこのpoll_idに投票済みかチェック
            $checkStmt = $this->pdo->prepare("SELECT id FROM votes WHERE poll_id = ? AND user_id = ?");
            $checkStmt->execute([$poll_id, $user_id]);
            if ($checkStmt->fetch()) {
                echo "User {$user_id} has already voted for poll {$poll_id}. Ignoring.\n";
                return; // 既に投票済みなら何もしない
            }

            // 2. 投票済みでなければ、トランザクション開始
            $this->pdo->beginTransaction();
            
            // 2a. votesテーブルに投票記録をINSERT
            $voteStmt = $this->pdo->prepare("INSERT INTO votes (poll_id, choice_id, user_id) VALUES (?, ?, ?)");
            $voteStmt->execute([$poll_id, $choice_id, $user_id]);
            
            // 2b. choicesテーブルの票数をUPDATE
            $choiceStmt = $this->pdo->prepare("UPDATE choices SET votes = votes + 1 WHERE id = ? AND poll_id = ?");
            $choiceStmt->execute([$choice_id, $poll_id]);

            $this->pdo->commit();
            echo "Vote recorded for poll {$poll_id}, choice {$choice_id} by user {$user_id}\n";

            // 3. 全員に最新情報をブロードキャスト
            $this->broadcastPollData($poll_id);

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            echo "Vote handling error: {$e->getMessage()}\n";
        }
    }

    private function broadcastPollData($poll_id) {
        $pollData = $this->getPollData($poll_id);
        if ($pollData) {
            foreach ($this->clients as $client) {
                $client->send(json_encode($pollData));
            }
            echo "Broadcasted update for poll {$poll_id}\n";
        }
    }
    
    private function sendPollData(ConnectionInterface $client, $poll_id) {
        $pollData = $this->getPollData($poll_id);
        if ($pollData) {
            $client->send(json_encode($pollData));
            echo "Sent initial data to client {$client->resourceId} for poll {$poll_id}\n";
        }
    }

    private function getPollData($poll_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT text, votes FROM choices WHERE poll_id = ? ORDER BY id ASC");
            $stmt->execute([$poll_id]);
            $choices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            foreach ($choices as $choice) {
                $labels[] = $choice['text'];
                $data[] = (int)$choice['votes'];
            }
            return ['type' => 'update', 'labels' => $labels, 'data' => $data];
        } catch (\Exception $e) {
            echo "Error fetching poll data: {$e->getMessage()}\n";
            return null;
        }
    }
}
