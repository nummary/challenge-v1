<?php 
session_start();

if (isset($_SESSION['uid'])) {
	require_once "db.php";

	if (isset($_GET['cid']) && isset($_GET['vote'])) {
		$comment_id = $_GET['cid'];
		$vote = $_GET['vote'];
		$user_id = $_SESSION['uid'];

		if (!in_array($vote, ['127827','128514','127876','128169','129505','128077','128078'])) {
		    header("Location: " . $_SERVER['HTTP_REFERER']); 
			exit();
		}

		$reaction_stmt = $pdo->prepare("SELECT `cid`, `vote` FROM `reactions` WHERE  `uid` = :uid AND `cid` = :cid");
		$reaction_stmt->execute(['uid' => $user_id, 'cid' => $comment_id]);
		$reaction = $reaction_stmt->fetch(PDO::FETCH_ASSOC);

		if ($reaction) {
			if ($reaction['vote'] == $vote) {
				$stmt = $pdo->prepare("DELETE FROM `reactions` WHERE `uid` = :uid AND `cid` = :cid");
				$stmt->execute([
					'uid' => $user_id,
					'cid' => $comment_id
				]);
			} else {
				$stmt = $pdo->prepare("UPDATE `reactions` SET `vote` = :vote WHERE `cid` = :cid AND `uid` = :uid");
				$stmt->execute([
					'uid' => $user_id,
					'cid' => $comment_id,
					'vote' => $vote
				]);
			}
		} else {
		    $stmt = $pdo->prepare("INSERT INTO `reactions` (`cid`, `uid`, `vote`) VALUES (:cid, :uid, :vote)");
		    $stmt->execute([
				'uid' => $user_id,
				'cid' => $comment_id,
				'vote' => $vote
			]);;
		}
		header("Location: " . $_SERVER['HTTP_REFERER']); 
		exit();
	} else {

	}
}

?>
