<?php
use micro\orm\DAO;
class Admin extends \BaseController {
//TODO 2.1
	public function index() {
		$count = (object)[];//initialisation des variables
		$count->all = (object)[];
		$count->today = (object)[];

		$count->all->user = DAO::count('utilisateur'); //on compte dans la BDD pour récupérer le nombre
		$count->all->disk = DAO::count('disque');
		$count->all->tarif = DAO::count('tarif');
		$count->all->service = DAO::count('service');

		$count->today->user = DAO::count('utilisateur', 'createdAt = NOW()');//on vérifie quand ca va etre créer
		$count->today->disk = DAO::count('disque', 'createdAt = NOW()');


		$this->loadView('Admin/index.html', ['count' => $count]);//charge la vue
	}
//TODO 2.2
	public function users() {
		$users = DAO::getAll('utilisateur');// on récupere tous les users 
		foreach($users as $user) { //pour chaque users on compte le nombre le disque
			$user->countDisk = DAO::count('disque', 'idUtilisateur = '. $user->getId()); // compte le nombre de disque relatif à l'user
			$user->disks = DAO::getAll('disque', 'idUtilisateur = '. $user->getId()); // on récupère tous les disques
			$user->diskTarif = 0;

			foreach($user->disks as $disk) { //pour chaque disque que possède l'user , on récupère les tarifs des disques et si pas null on ajoute le tarif au users
				$tarif = ModelUtils::getDisqueTarif($disk);
				if ($tarif != null)
					$user->diskTarif += $tarif->getPrix();
			}
		}

		$this->loadView('Admin/user.html', ['users' => $users]); //charge la vue
	}
//TODO 2.3
	public function disques() {
		$users = DAO::getAll('utilisateur');
    
		$i = 0;
		foreach($users as $user) { // pour chaque users 
			if($user->getAdmin() == 0)// si == 0 user si == 1 admin
				$user->status = 'Utilisateur';
			elseif ($user->getAdmin() == 1)
				$user->status = 'Administrateur';

			$user->disks = DAO::getAll('disque', 'idUtilisateur = '. $user->getId());// on récupere tous les disque de chaque user

			if(empty($user->disks)) // si l'utilistaeur n'a pas de disque, on supprime l'utilistaur
				unset($users[$i]);

			foreach($user->disks as $disk) // si y'a disque on récupere les tarifs
				$disk->tarif = ModelUtils::getDisqueTarif($disk);

			$i++;
		}

		$this->loadView('Admin/disques.html', ['users' => $users]); //charge la vue
	}
}