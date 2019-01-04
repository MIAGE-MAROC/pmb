<?php
// +-------------------------------------------------+
// � 2002-2005 PMB Services / www.sigb.net pmb@sigb.net et contributeurs (voir www.sigb.net)
// +-------------------------------------------------+
// $Id: lignes_actes.class.php,v 1.32 2018-12-20 11:00:19 mbertin Exp $

if (stristr($_SERVER['REQUEST_URI'], ".class.php")) die("no access");

if(!defined('TYP_ACT_CDE')) define('TYP_ACT_CDE', 0);	//				0 = Commande
if(!defined('TYP_ACT_DEV')) define('TYP_ACT_DEV', 1);	//				1 = Demande de devis
if(!defined('TYP_ACT_LIV')) define('TYP_ACT_LIV', 2);	//				2 = Bon de Livraison
if(!defined('TYP_ACT_FAC')) define('TYP_ACT_FAC', 3);	//				3 = Facture
if(!defined('TYP_ACT_RENT_ACC')) define('TYP_ACT_RENT_ACC', 4);	//		4 = Demande/D�compte de location
if(!defined('TYP_ACT_RENT_INV')) define('TYP_ACT_RENT_INV', 5);	//		5 = Facture de Location

if(!defined('STA_ACT_ALL')) define('STA_ACT_ALL', -1);	//Statut acte	-1 = Tous
if(!defined('STA_ACT_AVA')) define('STA_ACT_AVA', 1);	//				1 = A valider
if(!defined('STA_ACT_ENC')) define('STA_ACT_ENC', 2);	//				2 = En cours
if(!defined('STA_ACT_REC')) define('STA_ACT_REC', 4);	//				4 = Re�u/Livr�
if(!defined('STA_ACT_FAC')) define('STA_ACT_FAC', 8);	//				8 = Factur�
if(!defined('STA_ACT_PAY')) define('STA_ACT_PAY', 16);	//				16 = Pay�
if(!defined('STA_ACT_ARC')) define('STA_ACT_ARC', 32);	//				32 = Archiv�

class lignes_actes{
	
	
	public $id_ligne = 0;					//Identifiant de la ligne d'acte	
	public $type_ligne = 0;				//type de ligne de commande (0=texte, 1=notice, 2=bulletin, 3=frais, 4=abt, 5=article)
	public $num_acte = 0;					//Identifiant de l'acte auquel est rattach�e la ligne
	public $lig_ref = 0;					//Identifiant de la ligne de l'acte � laquelle est li�e cette ligne (pour commande ->livraison)
	public $num_acquisition = 0;			//Identifiant de la suggestion ayant d�clench� la commande (optionnel)
	public $num_rubrique = 0;				//Identifiant du num�ro de rubrique budg�taire � laquelle est affect�e la ligne d'acte
	public $num_produit = '';				//Identifiant de notice ou 0 si produit non g�r�
	public $num_type = '0';				//Identifiant du type de produit
	public $libelle = '';					//Libelle de la ligne de commande, reprend titre, editeur, auteur, collection, ...
	public $code = '';						//ISBN, ISSN, ...
	public $prix = '0.00';					//Prix de l'ouvrage
	public $tva = '0.00';					//Tva applicable sur l'ouvrage
	public $remise = '0.00';				//Remise sur ligne
	public $nb = 0;						//nb d'articles 					
	public $date_ech = '0000-00-00';		//Date d'�ch�ance
	public $date_cre = '0000-00-00';		//Date de cr�ation de ligne
	public $statut = 1;					//Statut de reception
	public $index_ligne = '';				//Index de recherche
	public $debit_tva = 0;
	public $commentaires_gestion = '';
	public $commentaires_opac = '';
	public $applicants = array(); 			//Demandeurs (utilis� seulement pour les commandes) pr�chargement des demendeurs issus des suggestions)
	 
	//Constructeur.	 
	public function __construct($id_ligne= 0) {
		$this->id_ligne = $id_ligne+0;
		if ($this->id_ligne) {
			$this->load();	
		}
	}	
	
	// charge une ligne d'acte � partir de la base.
	public function load(){
		global $acquisition_gestion_tva;
		
		$q = "select * from lignes_actes where id_ligne = '".$this->id_ligne."' ";
		$r = pmb_mysql_query($q) ;
		$obj = pmb_mysql_fetch_object($r);
		$this->type_ligne = $obj->type_ligne;
		$this->num_acte = $obj->num_acte;
		$this->lig_ref = $obj->lig_ref;
		$this->num_acquisition = $obj->num_acquisition;
		$this->num_rubrique = $obj->num_rubrique;
		$this->num_produit = $obj->num_produit;
		$this->num_type = $obj->num_type;
		$this->libelle = $obj->libelle;
		$this->code = $obj->code;
		$this->prix = $obj->prix;
		$this->tva = $obj->tva;
		$this->remise = $obj->remise;
		$this->nb = $obj->nb;
		$this->date_ech = $obj->date_ech;
		$this->date_cre = $obj->date_cre;
		$this->statut = $obj->statut;		
		$this->debit_tva = $obj->debit_tva;
		// Pour les anciennes commandes
		if(!$this->debit_tva)$this->debit_tva=$acquisition_gestion_tva;
		$this->commentaires_gestion = $obj->commentaires_gestion;
		$this->commentaires_opac = $obj->commentaires_opac;
	}
	
	// enregistre une ligne d'acte en base
	public function save(){
		global $acquisition_gestion_tva;
		
		if(!$this->debit_tva)$this->debit_tva=$acquisition_gestion_tva;
		
		if (!$this->num_acte) die("Erreur de cr�ation Lignes_Actes");
		
		if ($this->id_ligne) {
			
			$q = "update lignes_actes set type_ligne = '".$this->type_ligne."', num_acte = '".$this->num_acte."', lig_ref = '".$this->lig_ref."', num_acquisition = '".$this->num_acquisition."', ";
			$q.= "num_rubrique = '".$this->num_rubrique."', num_produit = '".$this->num_produit."', num_type = '".$this->num_type."', ";
			$q.= "libelle = '".$this->libelle."', code = '".$this->code."', prix = '".$this->prix."', tva = '".$this->tva."', nb = '".$this->nb."', debit_tva = '".$this->debit_tva."', ";
			$q.= "remise = '".$this->remise."', date_ech = '".$this->date_ech."', date_cre = '".$this->date_cre."', statut = '".$this->statut."', "; 
			$q.= "commentaires_gestion = '".$this->commentaires_gestion."', commentaires_opac = '".$this->commentaires_opac."', ";
			$q.= "index_ligne = ' ".strip_empty_words($this->libelle)." '";
			$q.= "where id_ligne = '".$this->id_ligne."' ";
			$r = pmb_mysql_query($q);

		} else {

			$q = "insert into lignes_actes set type_ligne = '".$this->type_ligne."', num_acte = '".$this->num_acte."', lig_ref = '".$this->lig_ref."', num_acquisition = '".$this->num_acquisition."', num_rubrique = '".$this->num_rubrique."', ";
			$q.= "num_produit = '".$this->num_produit."', num_type = '".$this->num_type."', libelle = '".$this->libelle."', code = '".$this->code."', prix = '".$this->prix."', tva = '".$this->tva."', nb = '".$this->nb."', debit_tva = '".$this->debit_tva."', ";
			$q.= "remise = '".$this->remise."', date_ech = '".$this->date_ech."', date_cre = '".today()."', statut = '".$this->statut."', ";
			$q.= "commentaires_gestion = '".$this->commentaires_gestion."', commentaires_opac = '".$this->commentaires_opac."', ";
			$q.= "index_ligne = ' ".strip_empty_words($this->libelle)." '";
			$r = pmb_mysql_query($q);
			$this->id_ligne = pmb_mysql_insert_id();
			
		}
	}


	//supprime une ligne d'acte de la base
	public function delete($id_ligne= 0) {
		if(!$id_ligne) $id_ligne = $this->id_ligne; 	

		$q = "delete from lignes_actes where id_ligne = '".$id_ligne."' ";
		$r = pmb_mysql_query($q);
	}


	//retourne les lignes de livraison pour une ligne de commande
	//Si num_acte est indiqu�, recherche uniquement dans les enregistrements de l'acte correspondant
	public static function getLivraisons($id_lig, $num_acte=0) {
		if ($num_acte) {
			$q = "select * from lignes_actes where lig_ref = '".$id_lig."' and num_acte = '".$num_acte."' order by id_ligne ";
		} else {
			$q = "select lignes_actes.* from actes,lignes_actes where actes.type_acte = '".TYP_ACT_LIV."' and lignes_actes.lig_ref = '".$id_lig."' ";
			$q.= "and lignes_actes.num_acte = actes.id_acte order by id_ligne ";
		}
		$r = pmb_mysql_query($q);
		return $r;
	}

	
	//retourne les lignes de facture pour une ligne de commande
	//Si num_acte est indiqu�, recherche uniquement dans les enregistrements de l'acte correspondant
	public static function getFactures($id_lig, $num_acte=0) {
		if ($num_acte) {
			$q = "select * from lignes_actes where lig_ref = '".$id_lig."' and num_acte = '".$num_acte."' order by id_ligne ";
		} else {
			$q = "select lignes_actes.* from actes,lignes_actes where actes.type_acte = '3' and lignes_actes.lig_ref = '".$id_lig."' ";
			$q.= "and lignes_actes.num_acte = actes.id_acte order by id_ligne ";
		}
		$r = pmb_mysql_query($q);
		return $r;
	}


	//optimization de la table lignes_actes
	public function optimize() {
		$opt = pmb_mysql_query('OPTIMIZE TABLE lignes_actes');
		return $opt;
	}
	
	//modification des lignes par lot
	public function updateFields($t_id=array(), $t_fields=array()) {
		if (count($t_id) && count($t_fields)) {
			$t=array();
			foreach($t_fields as $f=>$v) {
				$t[]= $f."='".$v."' ";
			}
			$q="update lignes_actes set ".implode(',',$t)." where id_ligne in ('".implode("','",$t_id)."') ";
			pmb_mysql_query($q);
		}		
	}
	
	//retourne une requete pour recuperation des lignes avec id d'acte et id fournisseur
	public static function getLines($tab_lig=array(), $relances=false) {
		$q='';
		if(count($tab_lig)) {
			$q = "select num_fournisseur, id_acte, id_ligne from lignes_actes join actes on actes.id_acte=lignes_actes.num_acte ";
			if($relances) $q.= "join lignes_actes_statuts on lignes_actes.statut=lignes_actes_statuts.id_statut and lignes_actes_statuts.relance='1' "; 
			$q.= "where id_ligne in ('".implode("','",$tab_lig)."') ";
			$q.= "order by num_fournisseur, id_acte, id_ligne ";
		}
		return $q;
	}
	
	//retourne un tableau des dates de relances sur une ligne
	public static function getRelances ($id_lig=0) {
		global $msg;
		$tab = array();
		if ($id_lig) { 
			$q = "select num_ligne, date_format(date_relance, '".$msg["format_date"]."') as date_rel ";
			$q.= "from lignes_actes_relances where num_ligne ='".$id_lig."' ";
			$q.= "order by num_ligne, date_relance desc ";
			$r = pmb_mysql_query($q);
			
			if (pmb_mysql_num_rows($r)) {
				while ($row=pmb_mysql_fetch_object($r)) {
					$tab[]=$row->date_rel;
				}
			}
		}
		return $tab;
	}

	
	//retourne un tableau des lignes de relances pour un fournisseur
	public static function getRelancesBySupplier ($id_fou=0) {
		global $msg;
		$tab = array();
		if ($id_fou) { 
			$q = "select id_acte, type_acte, date_format(date_acte, '".$msg["format_date"]."') as date_acte, numero as numero, ";
			$q.= "num_ligne, date_format(date_relance, '".$msg["format_date"]."') as date_rel , type_ligne, num_acquisition, num_rubrique, num_produit, num_type, ";
			$q.= "libelle, code, prix, tva, nb, lignes_actes_relances.statut as statut, remise, debit_tva, commentaires_gestion, commentaires_opac ";
			$q.= "from actes join lignes_actes_relances on num_acte=id_acte where num_fournisseur ='".$id_fou."' ";
			$q.= "order by date_relance desc, num_acte ";
			$r = pmb_mysql_query($q);
			
			if (pmb_mysql_num_rows($r)) {
				while ($row=pmb_mysql_fetch_array($r, PMB_MYSQL_ASSOC)) {
					$tab[]=$row;
				}
			}
		}
		return $tab;
	}
	
	//enregistre la relance d'un ensemble de lignes
	public static function setRelances ($tab_lig=array()) {
		if (count($tab_lig)) {
			$q1 = "select * from lignes_actes join lignes_actes_statuts on lignes_actes.statut=lignes_actes_statuts.id_statut and lignes_actes_statuts.relance='1' where id_ligne in ('".implode("','",$tab_lig)."') ";
			$r1 = pmb_mysql_query($q1);
			if (pmb_mysql_num_rows($r1)) {
				while ($row=pmb_mysql_fetch_object($r1)) {
					$q2 = "insert ignore into lignes_actes_relances set num_ligne = '".$row->id_ligne."' ,date_relance=curdate(), type_ligne = '".$row->type_ligne."', num_acte = '".$row->num_acte."', lig_ref = '".$row->lig_ref."', num_acquisition = '".$row->num_acquisition."', num_rubrique = '".$row->num_rubrique."', ";
					$q2.= "num_produit = '".$row->num_produit."', num_type = '".$row->num_type."', libelle = '".addslashes($row->libelle)."', code = '".addslashes($row->code)."', prix = '".$row->prix."', tva = '".$row->tva."', nb = '".$row->nb."', debit_tva = '".$row->debit_tva."', ";
					$q2.= "remise = '".$row->remise."', date_ech = '".$row->date_ech."', date_cre = '".today()."', statut = '".$row->statut."', ";
					$q2.= "commentaires_gestion = '".addslashes($row->commentaires_gestion)."', commentaires_opac = '".addslashes($row->commentaires_opac)."', ";
					$q2.= "index_ligne = ' ".addslashes($row->libelle)." '";
					pmb_mysql_query($q2);		
				}
			}
		}
	}
	
	public static function deleteRelances($id_fou=0, $id_acte=0) {
		$q='';
		if ($id_fou) {
			$q = "delete from lignes_actes_relances where num_acte in (select id_acte from actes where num_fournisseur='".$id_fou."' ) ";
		} elseif($id_acte) {
			$q = "delete from lignes_actes_relances where num_acte='".$id_acte."' ";
		}
		if ($q) {
			pmb_mysql_query($q);
		}
	}
	
	public function getNbDelivered($id_lig=0) {
		if(!$id_lig) $id_lig=$this->id_ligne;
		$q = "select ifnull(sum(nb),0) from lignes_actes join actes on id_acte=num_acte where actes.type_acte = '".TYP_ACT_LIV."' and lig_ref = '".$id_lig."' ";
		$r = pmb_mysql_result(pmb_mysql_query($q),0,0);
		return $r;
	}
	
	public function fetchApplicants(){
		if($this->id_ligne && $this->num_acte){
			$acte = new actes($this->num_acte);
			if($acte->type_acte == 0){ //Ligne issue d'un acte de type commande
				$query = "select empr_num from lignes_actes_applicants where ligne_acte_num = ".$this->id_ligne;
				$result = pmb_mysql_query($query);
				if(pmb_mysql_num_rows($result)){
					$applicants = array();
					while($row = pmb_mysql_fetch_row($result)){
						$this->applicants[] = $row[0];
					}
				}
				return 0;
			}
			return 0;
		}
		return 0;
	}
	
	public function getApplicants(){
		if(!count($this->applicants)){
			$this->fetchApplicants();
		}
		return $this->applicants;
	}
	
	public function treatApplicants($applicants){
		$this->applicants = array();
		if(is_array($applicants) && count($applicants)){
			foreach($applicants as $applicant){
				$applicant=intval($applicant);
				if($applicant != 0){
					$this->applicants[] = $applicant;
				}
			}
		}
	}
	
	public function saveApplicants(){
		$query = "delete from lignes_actes_applicants where ligne_acte_num = ".$this->id_ligne;
		pmb_mysql_query($query);
		
		if(count($this->applicants)){
			foreach($this->applicants as $applicant){
				$query = "insert into lignes_actes_applicants set ligne_acte_num=".($this->id_ligne*1).", empr_num=".$applicant." ";
				pmb_mysql_query($query);
			}
		}
	}
	

	//R�cup�ration sans instanciation. respect de ce qui est fait actuellement dans le fichier commmande.inc.php
	public static function getApplicantsFromId($id_ligne=0){
		$applicants = array();
		$query = 'select lignes_actes_applicants.empr_num from lignes_actes_applicants where lignes_actes_applicants.ligne_acte_num = '.$id_ligne;
		$result = pmb_mysql_query($query);
		if(pmb_mysql_num_rows($result)){
			while($row = pmb_mysql_fetch_object($result)){
				$applicants[] = $row->empr_num;
			}
		}
		return $applicants;
	}
	
	public static function transfer_lines($id_cde,$ids_line) {
		$id_cde+=0;

		if (!$ids_line || !$id_cde){
			return '';			
		}
		$query = "UPDATE lignes_actes set num_acte=".$id_cde." where id_ligne in (" . $ids_line . ")";
		pmb_mysql_query($query);
		
	}
	
	public static function duplicate_lines($id_cde,$ids_line) {
		$id_cde+=0;
	
		if (!$ids_line || !$id_cde){
			return '';
		}
		$query = "INSERT INTO lignes_actes (SELECT null, type_ligne, ".$id_cde.", lig_ref, num_acquisition, num_rubrique, num_produit, num_type, libelle, code, prix, tva, nb, date_ech, date_cre, statut, remise, index_ligne, ligne_ordre,	debit_tva, commentaires_gestion, commentaires_opac FROM lignes_actes WHERE id_ligne in (" . $ids_line . ") )";
		pmb_mysql_query($query);	
	}
}
