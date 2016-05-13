<?php
use micro\js\Jquery;
use micro\orm\DAO;

/**
 * Contrôleur permettant d'afficher/gérer 1 disque
 * @author jcheron
 * @version 1.1
 * @package cloud.controllers
 */
class Scan extends BaseController {

	public function index(){

	}

	/**
	 * Affiche un disque
	 * @param int $idDisque
	 * @return bool
	 * @throws Exception
	 */
	public function show($idDisque) {
		$user = Auth::getUser();// recup user
		$disque = DAO::getOne('disque', 'id ='. $idDisque);// recup disque

		$disqueNom = $disque->getNom();//recup nom disque
		$disque->occupation = DirectoryUtils::formatBytes($disque->getOccupation() / 100 * $disque->getQuota());
		$disque->occupationTotal = DirectoryUtils::formatBytes($disque->getQuota()); // même qu'avant

		if($disque->getOccupation() <= 100 && $disque->getOccupation() > 80) {
			$disque->status = 'Proche saturation';
			$disque->style = 'danger';
		}
		if($disque->getOccupation() <= 80 && $disque->getOccupation() > 50) {
			$disque->status = 'Forte occupation';
			$disque->style = 'warning';                                            // Bootstrap
		}
		if($disque->getOccupation() <= 50 && $disque->getOccupation() > 10) {
			$disque->status = 'RAS';
			$disque->style = 'success';
		}
		if($disque->getOccupation() <= 10 && $disque->getOccupation() > 0) {
			$disque->status = 'Peu occupé';
			$disque->style = 'info';
		}

		$disque->lesservices = DAO::getManyToMany($disque, 'services');// recup services disque
		$tarif = ModelUtils::getDisqueTarif($disque); // recup tarif disque


		$this->loadView("scan/vFolder.html", array('user' => $user, 'disque' => $disque, 'disqueNom' => $disqueNom, 'tarif' => $tarif));
		Jquery::executeOn("#ckSelectAll", "click", "$('.toDelete').prop('checked', $(this).prop('checked'));$('#btDelete').toggle($('.toDelete:checked').length>0)");
		Jquery::executeOn("#btUpload", "click", "$('#tabsMenu a:last').tab('show');");
		Jquery::doJqueryOn("#btDelete", "click", "#panelConfirmDelete", "show");
		Jquery::postOn("click", "#btConfirmDelete", "scan/delete", "#ajaxResponse", array("params" => "$('.toDelete:checked').serialize()"));
		Jquery::doJqueryOn("#btFrmCreateFolder", "click", "#panelCreateFolder", "toggle");
		Jquery::postFormOn("click", "#btCreateFolder", "Scan/createFolder", "frmCreateFolder", "#ajaxResponse");
		Jquery::execute("window.location.hash='';scan('" . $disqueNom . "')", true);
		echo Jquery::compile();
	}
//TODO 1.4
	public function rename($disqueId) { // il récupère les données du disques et de l'utilisateur puis les envoies à la vue
		$user = Auth::getUser();
		$disque = DAO::getOne('disque', 'id = '. $disqueId);
		$this->loadView('scan/rename.html', ['disque' => $disque, 'user' => $user]);
	}

	public function setNom() {
		if(!empty($_POST)) { 
			if(!isset($_POST['disqueId'])) { // vérifie si la variable est définie et pas nulle
				echo '<div class="alert alert-danger">Une erreur est survenue, veuillez réessayer ultérieurement</div>';
				echo '<a href="MyDisques/index" class="btn btn-primary btn-block">Revenir aux disques</a>';
				return false;
			}
			if(!isset($_POST['nom'])) {
				echo '<div class="alert alert-danger">Une erreur est survenue, veuillez réessayer ultérieurement</div>';
				echo '<a href="MyDisques/index" class="btn btn-primary btn-block">Revenir aux disques</a>';
				return false;
			}

			$user = Auth::getUser();
			$disque = DAO::getOne('disque', 'id = '. $_POST['disqueId']);
			$oldname = $disque->getNom();
			$disque->setNom($_POST['nom']);

			$path = $GLOBALS['config']['cloud']['root'] . $GLOBALS['config']['cloud']['prefix'] . $user->getLogin() . '/';
			rename($path . $oldname, $path . $_POST['nom']);

			if(DAO::update($disque)) {
				$this->forward('Scan', 'show', $_POST['disqueId']); //@URL
				return false;
			}
		}
	}
//TODO 1.5
	public function changeTarif($disqueId) {// Charge vue
		$user = Auth::getUser();
		$disque = DAO::getOne('disque', 'id = '. $disqueId);
		$tarifs = DAO::getAll('tarif');
		$this->loadView('scan/changeTarif.html', ['disque' => $disque, 'user' => $user, 'tarifs' => $tarifs]);
	}

	public function setTarif() {
		if(!empty($_POST)) {// si formulaire non vide
			if(!isset($_POST['disqueId'])) { // On vérifie que champ disqueID existe
				echo '<div class="alert alert-danger">Une erreur est survenue, veuillez réessayer ultérieurement</div>';//
				echo '<a href="MyDisques/index" class="btn btn-primary btn-block">Revenir aux disques</a>';
				return false;//Arret fonction
			}
			if(!isset($_POST['tarif'])) {// ON Vérifie si champ tarif existe
				echo '<div class="alert alert-danger">Une erreur est survenue, veuillez réessayer ultérieurement</div>';
				echo '<a href="MyDisques/index" class="btn btn-primary btn-block">Revenir aux disques</a>';
				return false;
			}

			$disque = DAO::getOne('disque', 'id = '. $_POST['disqueId']);// recup disque
			$disqueTarif = new DisqueTarif();// créé disque tarif/ lie disque et tarif
			$disqueTarif->setDisque($disque);// on ajoute disque

			$tarif = DAO::getOne('tarif', 'id = '. $_POST['tarif']);// recup tarif selectionné dans form
			$disqueTarif->setTarif($tarif); // On attribu tarif au disque
			$disqueTarif->setStartDate(date('Y-m-d H:m:s'));// + date

			$actual_size = $disque->getOccupation() / 100 * $disque->getTarif()->getQuota() * ModelUtils::sizeConverter($disque->getTarif()->getUnite());//Recupere occupation en octet
			$new_size = $tarif->getQuota() * ModelUtils::sizeConverter($tarif->getUnite()); // si taille actuelle est sup à nouvelle taille car sinon deuxieme disque plus petit
			if($actual_size > $new_size) {
				echo '<div class="alert alert-danger">Vous ne pouvez réduire l\'offre actuelle puisque votre quota est supérieur au nouveau</div>'; // Erreur
				echo '<a href="Scan/show/'. $_POST['disqueId'] .'" class="btn btn-primary btn-block">Revenir au disque</a>';
				return false;
			}
			else
				$disque->addTarif($disqueTarif);// On ajoute tarif au disque

			if (DAO::update($disque, true)) {// si bien executé on redirige vers Scan/show
				$this->forward('Scan', 'show', $_POST['disqueId']);
				return false;
			} else
				echo '<div class="alert alert-danger">Une erreur est survenue, veuillez rééssayer ultérieurement</div>';
		}
	}

	public function files($dir="Datas"){
		$cloud=$GLOBALS["config"]["cloud"];
		$root=$cloud["root"].$cloud["prefix"].Auth::getUser()->getLogin()."/";
		$response = DirectoryUtils::scan($root.$dir,$root);

		header('Content-type: application/json');
		echo json_encode(array(
				"name" => $dir,
				"type" => "folder",
				"path" => $dir,
				"items" => $response,
				"root" => $root
		));
	}

	public function upload(){
		$allowed = array('png', 'jpg', 'gif', 'zip');

		if(isset($_FILES['upl']) && $_FILES['upl']['error'] == 0){

			$extension = pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION);

			if(!in_array(strtolower($extension), $allowed)){
				echo '{"status":"error"}';
				exit;
			}

			if(move_uploaded_file($_FILES['upl']['tmp_name'], $_POST["activeFolder"].'/'.$_FILES['upl']['name'])){
				echo '{"status":"success"}';
				exit;
			}
		}

		echo '{"status":"error"}';
		exit;
	}

	/**
	 * Supprime le fichier dont le nom est fourni dans la clé toDelete du $_POST
	 */
	public function delete(){
		if(array_key_exists("toDelete", $_POST)){
			foreach ($_POST["toDelete"] as $f){
				unlink(realpath($f));
			}
			echo Jquery::execute("scan()");
			echo Jquery::doJquery("#panelConfirmDelete", "hide");

		}
	}

	/**
	 * Crée le dossier dont le nom est fourni dans la clé folderName du $_POST
	 */
	public function createFolder(){
		if(array_key_exists("folderName", $_POST)){
			$pathname=$_POST["activeFolder"].DIRECTORY_SEPARATOR.$_POST["folderName"];
			if(DirectoryUtils::mkdir($pathname)===false){
				$this->showMessage("Impossible de créer le dossier `".$pathname."`", "warning");
			}else{
				Jquery::execute("scan();",true);
			}
			Jquery::doJquery("#panelCreateFolder", "hide");
			echo Jquery::compile();
		}
	}

	/**
	 * Affiche un message dans une alert Bootstrap
	 * @param String $message
	 * @param String $type Class css du message (info, warning...)
	 * @param number $timerInterval Temps d'affichage en ms
	 * @param string $dismissable Alert refermable
	 * @param string $visible
	 */
	public function showMessage($message,$type,$timerInterval=5000,$dismissable=true){
		$this->loadView("main/vInfo",array("message"=>$message,"type"=>$type,"dismissable"=>$dismissable,"timerInterval"=>$timerInterval,"visible"=>true));
	}
}