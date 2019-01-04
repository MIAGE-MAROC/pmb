<?php
// +-------------------------------------------------+
// | 2002-2007 PMB Services / www.sigb.net pmb@sigb.net et contributeurs (voir www.sigb.net)
// +-------------------------------------------------+
// $Id: computed_field_used.class.php,v 1.2 2018-12-28 16:27:31 tsamson Exp $

if (stristr($_SERVER['REQUEST_URI'], ".class.php")) die("no access");

class computed_field_used {
	
	/**
	 * Identifiant
	 * @var 
	 */
	protected $id;
	
	/**
	 * Identifiant unique du champ dans l'arbre des sc�narios
	 */
	protected $field_num;
	
	protected $alias;
	
	protected $label;
	
	protected $value;
	
	/**
	 * Identifiant du champ calcul� associ�
	 * @var int
	 */
	protected $origine_field_num;
	
	public function __construct($id) {
		$id*= 1;
		$this->id = $id;
		$this->fetch_data();
	}
	
	private function fetch_data() {
		if ($this->id) {
			$query = "SELECT computed_fields_used_origine_field_num, computed_fields_used_label, computed_fields_used_num, computed_fields_used_alias
				FROM contribution_area_computed_fields_used
				WHERE id_computed_fields_used = '".$this->id."'";
			$result = pmb_mysql_query($query);
			if (pmb_mysql_num_rows($result)) {
				$row = pmb_mysql_fetch_assoc($result);
				$this->label = $row['computed_fields_used_label'];
				$this->alias = $row['computed_fields_used_alias'];
				$this->field_num = $row['computed_fields_used_num'];
				$this->origine_field_num = $row['computed_fields_used_origine_field_num'];
				$this->value = "";
				
				//variables d'environnement
				global ${$this->field_num};
				
				if (isset(${$this->field_num})) {
				    $this->value = ${$this->field_num};
				}
			}
		}
	}
	
	public function set_field_num($field_num) {
		$this->field_num = $field_num;
	}
	
	public function set_alias($alias) {
		$this->alias = $alias;
	}
	
	public function set_label($label) {
		$this->label = $label;
	}
	
	public function set_origine_field_num($origine_field_num) {
		$this->origine_field_num = $origine_field_num;
	}
	
	public function save() {
		if (!$this->id) {
			$query = "INSERT INTO contribution_area_computed_fields_used";
			$clause = "";
		} else {
			$query = "UPDATE contribution_area_computed_fields_used";
			$clause = " WHERE id_computed_fields_used = '".$this->id."'";
		}
		$query.= " SET computed_fields_used_origine_field_num = '".$this->origine_field_num."',
					computed_fields_used_label = '".$this->label."',
					computed_fields_used_num = '".$this->field_num."',
					computed_fields_used_alias = '".$this->alias."'";
		pmb_mysql_query($query.$clause);
		if (!$this->id) {
			$this->id = pmb_mysql_insert_id();
		}
	}
	
	public function get_data() {
		$data = array(
				'id' => $this->id,
				'label' => $this->label,
				'alias' => $this->alias,
				'field_num' => $this->field_num,
				'value' => $this->value
		);
		return $data;
	}
	
	public function get_id() {
		return $this->id;
	}
}
