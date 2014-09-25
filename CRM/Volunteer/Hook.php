<?php

class CRM_Volunteer_Hook {

  /**
   * hook_civicrm_volunteer_searchColumns is used when building the list of columns for a custom search.
   *
   * @param string $searchName The name of the custom search
   * @param array $columns Reference to the columns array: SQL column names keyed
   *        by printable column headers
   * @return null The return value is ignored
   */
  public static function searchColumns ($searchName, &$columns) {
    return CRM_Utils_Hook::singleton()->invoke(2, $searchName, $columns, CRM_Utils_Hook::$_nullObject,
      CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject,
      'civicrm_volunteer_searchColumns');
  }
}