<?php

/**
 * A custom contact search
 */
class CRM_Volunteer_Form_Search_Volunteer extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

  /**
   * The custom search ID, which is really the value of an OptionValue
   * in the special custom_search OptionGroup.
   *
   * @var Int
   */
  private static $customSearchId;

  /**
   * The ID of the Volunteer Need from which this search was initiated.
   *
   * @var string
   */
  private $volNeedId;

  /**
   * The ID of the Volunteer Project from which this search was initiated.
   *
   * @var string
   */
  private $volProjectId;

  function __construct(&$formValues) {
    parent::__construct($formValues);

    $this->volNeedId = $this->_getParam('vol_need');
    $this->volProjectId = $this->_getParam('vol_project');
  }

  /**
   * Helper function to get the value of params passed through the URL
   *
   * Since params may not be preserved in the URL on the second or third form
   * submission, we also check the value of hidden fields in the form.
   *
   * @param string $name
   * @param string $type See CRM_Utils_Type::validate() for acceptable values
   * @return string
   */
  private function _getParam($name, $type = 'Int') {
    $requestValue = CRM_Utils_Request::retrieve($name, $type);
    return ($requestValue ? $requestValue : CRM_Utils_Array::value($name, $this->_formValues));
  }

  /**
   * Returns the custom search ID, retrieving it from database if necessary.
   *
   * @return Int
   */
  public static function getCustomSearchId() {
    if (is_null(self::$customSearchId)) {
      self::$customSearchId = (int) civicrm_api3('CustomSearch', 'getvalue', array(
        'name' => "CRM_Volunteer_Form_Search_Volunteer",
        'return' => "value",
      ));
    }
    return self::$customSearchId;
  }

  /**
   * Create a task list based on the open needs of the volunteer project.
   *
   * @param CRM_Core_Form_Search $form
   * @return array
   */
  function buildTaskList(CRM_Core_Form_Search $form) {
    $tasks = array();

    $project = CRM_Volunteer_BAO_Project::retrieveByID($this->volProjectId);
    foreach ($project->open_needs as $need_id => $data) {
      $tasks[VOL_TASK_ASSIGN . "[$need_id]"] = ts('Assign to Volunteer Need: %1 (%2)', array(1 => $data['role_label'], 2=> $data['label'], 'domain' => 'org.civicrm.volunteer'));
    }

    return $tasks;
  }

  /**
   * Prepare a set of search fields
   *
   * @param CRM_Core_Form_Search $form modifiable
   * @return void
   */
  function buildForm(&$form) {
    $templateElements = array('state_province_id');
    CRM_Utils_System::setTitle(ts('Find Volunteer Candidates'));

    $apiResult = civicrm_api3('CustomGroup', 'getsingle', array(
      'extends' => 'Individual',
      'name' => 'Volunteer_Information',
      'api.customField.get' => array(
        'is_active' => 1,
        'is_searchable' => 1,
      ),
    ));
    $custom_fields = $apiResult['api.customField.get']['values'];
    foreach ($custom_fields as $field) {
      $templateElements[] = $field['name'];
      $this->addCustomField($field, $form);
    }

    $stateProvince = array('' => ts('- any state/province -')) + CRM_Core_PseudoConstant::stateProvince();
    $form->addElement('select', 'state_province_id', ts('State/Province'), $stateProvince);

    $form->add('hidden', 'vol_need', $this->volNeedId);
    $form->add('hidden', 'vol_project', $this->volProjectId);

    // Optionally define default search values
    $form->setDefaults(array(
      'state_province_id' => NULL,
    ));

    /**
     * if you are using the standard template, this array tells the template what elements
     * are part of the search criteria
     */
    $form->assign('elements', $templateElements);
  }

  /**
   * Get a list of displayable columns
   *
   * @return array, keys are printable column headers and values are SQL column names
   */
  function &columns() {
    // return by reference
    $columns = array(
      ts('Name', array('domain' => 'org.civicrm.volunteer')) => 'sort_name',
      ts('Email', array('domain' => 'org.civicrm.volunteer')) => 'email',
      ts('Phone', array('domain' => 'org.civicrm.volunteer')) => 'phone',
      ts('City', array('domain' => 'org.civicrm.volunteer')) => 'city',
      ts('State', array('domain' => 'org.civicrm.volunteer')) => 'state_province',
    );
    CRM_Volunteer_Hook::searchColumns(__CLASS__, $columns);
    return $columns;
  }

  /**
   * Construct a full SQL query which returns one page worth of results
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   * @return string, sql
   */
  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    // delegate to $this->sql(), $this->select(), $this->from(), $this->where(), etc.
    return $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
  }

  /**
   * Construct a SQL SELECT clause
   *
   * @return string, sql fragment with SELECT arguments
   */
  function select() {
    return "
      contact_a.id           as contact_id  ,
      contact_a.contact_type as contact_type,
      contact_a.sort_name    as sort_name,
      state_province.name    as state_province
    ";
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string, sql fragment with FROM and JOIN clauses
   */
  function from() {
    return "
      FROM      civicrm_contact contact_a
      LEFT JOIN civicrm_address address ON ( address.contact_id       = contact_a.id AND
                                             address.is_primary       = 1 )
      LEFT JOIN civicrm_email           ON ( civicrm_email.contact_id = contact_a.id AND
                                             civicrm_email.is_primary = 1 )
      LEFT JOIN civicrm_state_province state_province ON state_province.id = address.state_province_id
    ";
  }

  /**
   * Construct a SQL WHERE clause
   *
   * @param bool $includeContactIDs
   * @return string, sql fragment with conditional expressions
   */
  function where($includeContactIDs = FALSE) {
    $params = array();
    $where = '1';

    $count  = 1;
    $clause = array();

    $state = CRM_Utils_Array::value('state_province_id',
      $this->_formValues
    );
    if (!$state &&
      $this->_stateID
    ) {
      $state = $this->_stateID;
    }

    if ($state) {
      $params[$count] = array($state, 'Integer');
      $clause[] = "state_province.id = %{$count}";
    }

    if (!empty($clause)) {
      $where .= ' AND ' . implode(' AND ', $clause);
    }

    return $this->whereClause($where, $params);
  }

  /**
   * Determine the Smarty template for the search screen
   *
   * @return string, template path (findable through Smarty template path)
   */
  function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Modify the content of each row
   *
   * @param array $row modifiable SQL result row
   * @return void
   */
  function alterRow(&$row) {
//    $row['sort_name'] .= ' ( altered )';
  }

  /**
   * This is a quick and dirty placeholder. There *must* be a better way to do this
   *
   * @param array $field The values part of the result for api.customField.get
   * @param CRM_Core_Form $form modifiable
   */
  private function addCustomField($field, &$form) {
    $type = strtolower($field['html_type']);

    switch ($type) {
      case 'multi-select':
        $options = $this->buildOptionsList($field['option_group_id']);
        $form->addElement('select', $field['name'], $field['label'], $options);
        break;
      default:
        $form->add($type,
          $field['name'],
          $field['label']
        );
    }
  }

  private function buildOptionsList($optionGroupID) {
    $options = array();
    $apiResult = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => $optionGroupID,
    ));
    foreach ($apiResult['values'] as $id => $data) {
      $options[$data['value']] = $data['label'];
    }
    return $options;
  }
}
