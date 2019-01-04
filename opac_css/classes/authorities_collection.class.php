<?php
// +-------------------------------------------------+
// � 2002-2004 PMB Services / www.sigb.net pmb@sigb.net et contributeurs (voir www.sigb.net)
// +-------------------------------------------------+
// $Id: authorities_collection.class.php,v 1.4 2018-09-19 09:59:29 tsamson Exp $

/**
 * Classe de collection d'autorit�s pour �viter d'instancier plusieurs fois les m�mes autorit�s dans une m�me page
 * @author apetithomme
 *
 */
class authorities_collection {
	static private $authorities = array();
	
	static public function get_authority($authority_type, $authority_id, $params = array()) {
		$authority_id = $authority_id*1;
		if (!$authority_type) {
			return null;
		}
		if (isset($params['num_object']) && isset($params['type_object']) && isset(self::$authorities[$authority_type][$params['num_object'].'_'.$params['type_object']])) {
		    return self::$authorities[$authority_type][$params['num_object'].'_'.$params['type_object']];
		}
		if (isset(self::$authorities[$authority_type][$authority_id])) {
			return self::$authorities[$authority_type][$authority_id];
		}
		
		if (!isset(self::$authorities[$authority_type])) {
			self::$authorities[$authority_type] = array();
		}
		
		switch($authority_type){
			case "author" :
			case AUT_TABLE_AUTHORS :
				self::load_class("author");
				self::$authorities[$authority_type][$authority_id] = new auteur($authority_id);
				break;
			case "publisher" :
			case AUT_TABLE_PUBLISHERS :
				self::load_class("publisher");
				self::$authorities[$authority_type][$authority_id] = new publisher($authority_id);
				break;
			case "collection" :
			case AUT_TABLE_COLLECTIONS :
				self::load_class("collection");
				self::$authorities[$authority_type][$authority_id] = new collection($authority_id);
				break;
			case "subcollection" :
			case AUT_TABLE_SUB_COLLECTIONS :
				self::load_class("subcollection");
				self::$authorities[$authority_type][$authority_id] = new subcollection($authority_id);
				break;
			case "serie" :
			case AUT_TABLE_SERIES :
				self::load_class("serie");
				self::$authorities[$authority_type][$authority_id] = new serie($authority_id);
				break;
			case "indexint" :
			case AUT_TABLE_INDEXINT :
				self::load_class("indexint");
				self::$authorities[$authority_type][$authority_id] = new indexint($authority_id);
				break;
			case "titre_uniforme" :
			case AUT_TABLE_TITRES_UNIFORMES :
				self::load_class("titre_uniforme");
				self::$authorities[$authority_type][$authority_id] = new titre_uniforme($authority_id);
				break;
			case "category" :
			case AUT_TABLE_CATEG :
				global $lang;
				self::load_class("categorie");
				self::$authorities[$authority_type][$authority_id] = new categorie($authority_id,$lang);
				break;
			case "concept" :
			case AUT_TABLE_CONCEPT :
				self::load_class("skos/skos_concept");
				self::$authorities[$authority_type][$authority_id] = new skos_concept($authority_id);
				break;
			case "authperso" :
			case AUT_TABLE_AUTHPERSO :
				self::load_class("authperso_authority");
				self::$authorities[$authority_type][$authority_id] = new authperso_authority($authority_id);
				break;
			case 'authority' :
			case AUT_TABLE_AUTHORITY :
			    if($authority_id > 0){
			        $aut = new authority($authority_id);
			    }else{
			        $aut = new authority($authority_id,$params['num_object'],$params['type_object']);
			        $authority_id = $aut->get_id();
			    }
			    self::$authorities[$authority_type][$authority_id] = $aut;
			    self::$authorities[$authority_type][$aut->get_num_object().'_'.$aut->get_type_object()] = $aut;
			    break;
			default :
				return null;
		}
		return self::$authorities[$authority_type][$authority_id];
	}
	
	static private function load_class($classname) {
		global $base_path,$include_path,$class_path,$javascript_path,$style_path;
		require_once($class_path."/".$classname.".class.php");
	} 
}