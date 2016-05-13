<?php
use micro\controllers\Controller;
use micro\js\Jquery;
use micro\utils\RequestUtils;
use micro\orm\DAO;

class MyDisques extends BaseController{

	public function initialize(){
		if(!RequestUtils::isAjax()){
			$this->loadView('main/vHeader.html', array('infoUser' => Auth::getInfoUser()));
		}
	}
//TODO 1.1
	public function index() {
		echo Jquery::compile();
		$user = Auth::getUser();//Recup toutes les données de l'utilisateur
		$user_id = $user->getId();// On prend l'id a part
		$disques = \micro\orm\DAO::getAll('disque', 'idUtilisateur = '. $user_id); // On récupere tout les disques de l'user
		foreach($disques as $disque) {
			$disque->occupation = DirectoryUtils::formatBytes($disque->getOccupation() / 100 * $disque->getQuota());// Recup l'occupation en octect (calcul math)
			$disque->occupationTotal = DirectoryUtils::formatBytes($disque->getQuota());// Taille maximale octet

			if($disque->getOccupation() <= 100 && $disque->getOccupation() > 80) {
				$disque->progressStyle = 'danger';
			}
			if($disque->getOccupation() <= 80 && $disque->getOccupation() > 50) { //Bootstrap
				$disque->progressStyle = 'warning';
			}
			if($disque->getOccupation() <= 50 && $disque->getOccupation() > 10) {
				$disque->progressStyle = 'success';
			}
			if($disque->getOccupation() <= 10 && $disque->getOccupation() > 0) {
				$disque->progressStyle = 'info';
			}
		}

		$this->loadView('MyDisques/index.html', array('user' => $user, 'disques' => $disques)); // Charge vue et envoye données user et disque a la vue
    }
    
//TODO1.2
	public function frm() { // charge vue
		$this->loadView('MyDisques/create.html');
	}

	public function update() {
		if(isset($_POST) && !empty($_POST)) { // Verifie si le frm est soumis et si pas vide
			$error = false; // 

			if(empty($_POST['nom'])) {// si le nom est vide
				echo '<div class="alert alert-danger">Le nom ne doit pas être vide</div>';// affiche erreur
				$error = true;
			}

			if(!$error) {// si pas erreur
				$user = Auth::getUser();//recup user
				$name = $_POST['nom'];//recup nom

				$disque = new Disque();//créé disque
				$disque->setUtilisateur($user);//attribue disque à user
				$disque->setNom($name);// donne nom au disque

				if(DAO::insert($disque, true)) {//si présent dans dans bdd
					$cloud = $GLOBALS['config']['cloud'];
					$path = $cloud['root'] . $cloud['prefix'] . $user->getLogin() . '/' . $name;// recupere directory
					mkdir($path);// Créer un dossier

					$this->forward('Scan', 'show', $disque->getId());// redirige user vers une autre page /scan/show/Iddisque
					return false;// Arrete fonction
				}
			}
		}
	}

	public function finalize(){
		if(!RequestUtils::isAjax()){
			$this->loadView("main/vFooter.html");
		}
	}

