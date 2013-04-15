<?php
class UserData {
	const GENDER_FEMALE = 'F';
	const GENDER_MALE = 'M';
	const GENDER_UNKNOWN = '?';

	private $blocked = false;
	private $profilePicURL = null;
	private $gender = self::GENDER_UNKNOWN;
	private $joinDate = null;
	private $birthday = null;
	private $location = null;
	private $website = null;
	private $commentCount = null;
	private $postCount = null;

	public function getLocation() {
		return $this->location;
	}

	public function setLocation($location) {
		$this->location = $location;
	}

	public function getBirthday() {
		return $this->birthday;
	}

	public function setBirthday($birthday) {
		$this->birthday = $birthday;
	}

	public function getCommentCount() {
		return $this->commentCount;
	}

	public function setCommentCount($count) {
		$this->commentCount = $count;
	}

	public function getPostCount() {
		return $this->postCount;
	}

	public function setPostCount($count) {
		$this->postCount = $count;
	}

	public function getWebsite() {
		return $this->website;
	}

	public function setWebsite($website) {
		$this->website = $website;
	}

	public function isBlocked() {
		return $this->blocked;
	}

	public function setBlocked($blocked) {
		$this->blocked = $blocked;
	}

	public function getGender() {
		return $this->gender;
	}

	public function setGender($gender) {
		$this->gender = $gender;
	}

	public function getJoinDate() {
		return $this->joinDate;
	}

	public function setJoinDate($joinDate) {
		$this->joinDate = $joinDate;
	}

	public function getProfilePictureURL() {
		if (basename($this->profilePicURL) == 'na.gif') {
			return null;
		}
		return $this->profilePicURL;
	}

	public function setProfilePictureURL($url) {
		$this->profilePicURL = $url;
	}

}

class UserEntry extends AbstractModelEntry {
	private $anonymous = null;
	private $userName = null;
	private $userData = null;
	private $id = null;
	private $lists = [
		AMModel::TYPE_ANIME => false,
		AMModel::TYPE_MANGA => false
	];
	private $history = [
		AMModel::TYPE_ANIME => false,
		AMModel::TYPE_MANGA => false
	];
	private $friends = [];
	private $clubs = [];

	private static $runtimeIDCounter = 1;
	private $runtimeID;

	/* keep runtime id from caching by serialize() */
	public function __sleep() {
		$x = get_object_vars($this);
		unset($x['runtimeID']);
		unset($x['runtimeIDCounter']);
		$x = array_keys($x);
		return $x;
	}
	public function __wakeUp() {
		$this->runtimeID = self::$runtimeIDCounter;
		++ self::$runtimeIDCounter;
	}

	/* runtime id - each user "handled" during one request by web browser has higher id, starting from 1 */
	public function getRuntimeID() {
		return $this->runtimeID;
	}

	public function __construct($userName) {
		$this->runtimeID = self::$runtimeIDCounter;
		++ self::$runtimeIDCounter;
		$this->userName = $userName;
		$this->userData = new UserData();
	}

	public function __destruct() {
		$this->lists[AMModel::TYPE_ANIME]->destroy();
		$this->lists[AMModel::TYPE_MANGA]->destroy();
	}

	public function getAnonymousName() {
		$anonName = AnonService::getByUserName($this->getUserName());
		if ($anonName === null) {
			do {
				$alpha = '0123456789abcdefghijklmnopqrstuvwxyz';
				$anonName = '=';
				foreach (range(1, 8) as $k) {
					$anonName .= $alpha{mt_rand() % strlen($alpha)};
				}
			} while (AnonService::getByAnonName($anonName) !== null);
			$this->anonName = $anonName;
			AnonService::setPair($this->getUserName(), $anonName);
		}
		return $anonName;
	}

	public function getUserName() {
		return $this->userName;
	}

	public function setUserName($userName) {
		$this->userName = $userName;
	}

	public function getLinkableName() {
		if ($this->isAnonymous()) {
			return $this->getAnonymousName();
		}
		return $this->getUserName();
	}

	public function getPublicName() {
		if ($this->isAnonymous()) {
			return 'Anon (' . $this->getAnonymousName() . ')';
		}
		return $this->getUserName();
	}

	public function isAnonymous() {
		return $this->anonymous;
	}

	public function setAnonymous($anonymous = true) {
		$this->anonymous = $anonymous;
	}

	public function getUserData() {
		return $this->userData;
	}

	public function getList($type) {
		if (!isset($this->lists[$type])) {
			throw new Exception('Trying to get list of unknown type');
		}
		return $this->lists[$type];
	}

	public function getAnimeList() {
		return $this->getList(AMModel::TYPE_ANIME);
	}

	public function getMangaList() {
		return $this->getList(AMModel::TYPE_MANGA);
	}

	public function setList($type, $list) {
		if (!isset($this->lists[$type])) {
			throw new Exception('Trying to set list of unknown type');
		}
		$this->lists[$type] = $list;
	}

	public function setHistory($type, $history) {
		if (!isset($this->history[$type])) {
			throw new Exception('Trying to set history of unknown type');
		}
		$this->history[$type] = $history;
	}

	public function getHistory($type) {
		if (!isset($this->history[$type])) {
			throw new Exception('Trying to get history of unknown type');
		}
		return $this->history[$type];
	}

	public function getAnimeHistory() {
		return $this->getHistory(AMModel::TYPE_ANIME);
	}

	public function getMangaHistory() {
		return $this->getHistory(AMModel::TYPE_MANGA);
	}


	public function getFriends() {
		return $this->friends;
	}

	public function addFriend($friend) {
		$this->friends []= $friend;
	}

	public function resetFriends() {
		$this->friends = [];
	}

	public function getClubs() {
		return $this->clubs;
	}

	public function addClub($club) {
		$this->clubs []= $club;
	}

	public function resetClubs() {
		$this->clubs = [];

	}

	public function getID() {
		return $this->id;
	}

	public function setID($id) {
		$this->id = $id;
	}

}
