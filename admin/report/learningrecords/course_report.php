<?php

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/admin/report/learningrecords/filters/lib.php');
require_once($CFG->dirroot.'/local/mitms.php');
require_once($CFG->dirroot.'/hierarchy/lib.php');
require_once($CFG->dirroot.'/local/reportlib.php');

define('DEFAULT_PAGE_SIZE', 40);
define('SHOW_ALL_PAGE_SIZE', 5000);

$spage     = optional_param('spage', 0, PARAM_INT);                    // which page to show
$perpage   = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT);

admin_externalpage_setup('reportlearningrecords');
admin_externalpage_print_header();


// include SQL snippets for building query
require_once('query_snippets.php');

// specify source table
$source = 'course_completion';

// specify columns to be retrieved and displayed
// if the heading key is blank, field will be retrieved
// from DB but not displayed in table (useful to get IDs for linking)
$columns = array(
    array(
        'type'    => 'course',
        'value'   => 'fullname',
        'heading' => 'Course Name',
    ),
    // get course id but don't display column
    array(
        'type'    => 'course',
        'value'   => 'id',
    ),
    array(
        'type'    => 'user',
        'value'   => 'fullname',
        'heading' => 'Participant',
    ),
    // get user id but don't display column
    array(
        'type'    => 'user',
        'value'   => 'id',
    ),
    array(
        'type'    => 'user',
        'value'   => 'organisation',
        'heading' => 'Office',
    ),
    array(
        'type'    => 'user_profile',
        'value'   => 'dob',
        'heading' => 'DOB',
    ),
    array(
        'type'    => 'user',
        'value'   => 'position',
        'heading' => 'Role',
    ),
    array(
        'type'    => 'course_completion',
        'value'   => 'status',
        'heading' => 'Completion Status',
    ),
    array(
        'type'    => 'course_completion',
        'value'   => 'completeddate',
        'heading' => 'Completion Date',
    ),
    array(
        'type'    => 'user',
        'value'   => 'area_office',
        'heading' => 'AO',
        'level'   => '3',
    ),
    array(
        'type'    => 'user',
        'value'   => 'conservancy_office',
        'heading' => 'CO',
        'level'   => '2',
    ),
    array(
        'type'    => 'user',
        'value'   => 'regional_office',
        'heading' => 'RO',
        'level'   => '1',
    ),
);

// specify filter options
$fieldinfo = array(
    array(
        'type' => 'user',
        'value' => 'fullname',
        'advanced' => 0,
    ),
    array(
        'type' => 'user',
        'value' => 'firstname',
        'advanced' => 1,
    ),
    array(
        'type' => 'user',
        'value' => 'lastname',
        'advanced' => 1,
    ),
    array(
        'type' => 'course',
        'value' => 'fullname',
        'advanced' => 0,
    ),
    array(
        'type' => 'course_category',
        'value' => 'id',
        'advanced' => 1,
    ),
    array(
        'type' => 'course_completion',
        'value' => 'completeddate',
        'advanced' => 1,
    ),
);

//TODO use current page url
$returnurl = "{$CFG->wwwroot}/admin/report/learningrecords/course_report.php" ;

// build filter to get where clause
$filtering = new filtering($source, $fieldinfo, $snippets, $returnurl);
$extrasql = $filtering->get_sql_filter();

if($extrasql && $extrasql!='') {
    $where = "WHERE $extrasql";
} else {
    $where = '';
}

// build sql query
// note that this function looks at session var set by filtering above
// so needs to be *after* filtering defined
$sql = build_query($columns, $source, $snippets);

$debug = false;
if ($debug) {
    print $sql.$where;
}

// count results with and without filtering
$countsql = build_query($columns, $source, $snippets, true);
$countall = count_records_sql($countsql);
$countfiltered = count_records_sql($countsql.$where);

// display heading including filtering stats
print_heading(get_string('report:learningrecords', 'local').": Showing $countfiltered / $countall");

// show filter form elements
$filtering->display_add();
$filtering->display_active();

// build the table
$table = new flexible_table('-learningrecords');

foreach($columns as $column) {
    // don't print a column if heading is blank
    if(isset($column['heading']) && $column['heading'] != '') {
        $tablecolumns[] = $column['heading'];
        $tableheaders[] = $column['heading'];
    }
}
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);

$table->column_style('edit','width','80px');

$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'recordoflearning');
$table->set_attribute('class', 'logtable generalbox');

$table->set_control_variables(array(
            TABLE_VAR_SORT    => 'ssort',
            TABLE_VAR_HIDE    => 'shide',
            TABLE_VAR_SHOW    => 'sshow',
            TABLE_VAR_IFIRST  => 'sifirst',
            TABLE_VAR_ILAST   => 'silast',
            TABLE_VAR_PAGE    => 'spage'
            ));
$table->setup();

$table->initialbars(true);

$table->pagesize($perpage, $countfiltered);

$records = get_recordset_sql($sql.$where,
    $table->get_page_start(),  $table->get_page_size());

$org_cache = array();

if ($records) {
    while ($record = rs_fetch_next_record($records)) {
        $tabledata = array();
        foreach ($columns as $column) {
            // don't print a column if heading is blank
            if(isset($column['heading']) && $column['heading'] != '') {
                $type = $column['type'];
                $value = $column['value'];
                $field = "{$type}_{$value}";
                // add conditions here to treat certain fields differently
                if ($field == 'course_startdate' || $field == 'course_completion_completeddate') {
                    // show timestamp as date or blank if not set
                    $tabledata[] = nice_date($record->$field);
                } else if ($field == 'user_fullname' && isset($record->user_id)) {
                    // link name to profile page if id available
                    $tabledata[] = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$record->user_id.'">'.$record->$field.'</a>';
                } else if ($field == 'course_fullname' && isset($record->course_id)) {
                    // link name to course page if id available
                    $tabledata[] = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$record->course_id.'">'.$record->$field.'</a>';
                } else if (preg_match('/^user_.*_office$/i',$field)) {
                    // basic caching to reduce calls to hierarchy_lineage
                    if(array_key_exists($record->$field,$org_cache)){
                        $orgs = $org_cache[$record->$field];
                    } else {
                        $orgs = mitms_get_user_hierarchy_lineage($record->$field,'organisation');
                        $org_cache[$record->$field] = $orgs;
                    }
                    $desc = '';
                    foreach ($orgs as $org) {
                        if($column['level'] == $org->depthlevel) {
                            $desc = $org->fullname;
                        }
                    }
                    $tabledata[] = $desc;
                } else {
                    // just print the field
                    $tabledata[] = $record->$field;
                }
            }
        }
        $table->add_data($tabledata);
    }
    rs_close($records);
}

$table->print_html();

// Get Data for Table


admin_externalpage_print_footer();


function nice_date($timestamp) {
    if($timestamp && $timestamp > 0) {
        return userdate($timestamp,'%d %B %Y');
    } else {
        return '';
    }
}

function build_query($columns, $source, $snippets, $countonly=false) {
    global $CFG,$joinlist;

    $info = $snippets[$source];
    if(!isset($info) || !isset($info['base'])) {
        die("build_query(): Invalid source '$source'");
    }
    // get the fields needed to display requested columns
    $fields = get_column_fields($source, $columns, $info);

    // get the joins needed to display requested columns and do filtering
    $columnjoins = get_column_joins($source, $columns, $info, $joinlist);
    $filterjoins = get_filter_joins($source, $info, $joinlist);
    $joins = array_merge($columnjoins, $filterjoins);

    // now build the query from the snippets

    // need a unique field for get_records() so include id as first column
    if($countonly) {
        $select = "SELECT COUNT(*) ";
    } else {
        $select = "SELECT base.id,".implode($fields,',')." ";
    }
    // sort joins in order determined by sort_join function
    // this ensures joins are processed in the correct order
    uksort($joins, 'sort_join');

    // build query starting from base table then adding required joins
    $from = "FROM {$snippets[$source]['base']} ".implode($joins,' ')." ";

    $sql = "$select $from";
    return $sql;
}

function get_column_fields($source, $columns, $info) {
    $fields = array();
    foreach($columns as $column) {
        $type = isset($column['type']) ? $column['type'] : '';

        if(array_key_exists($type, $info)) {
            $value = isset($column['value']) ? $column['value'] : '';

            if(array_key_exists($value, $info[$type])) {
                // add field to list to be selected
                // use type_value as alias for each field
                $fields[] = $info[$type][$value]['field']." ".sql_as()." {$type}_{$value}";
            } else {
                trigger_error("get_column_fields(): column value '$value' not found in source '$source' for type '$type'",E_USER_ERROR);
            }
        } else {
            trigger_error("get_column_fields(): column type '$type' not found in source '$source'",E_USER_ERROR);
        }
    }
    return $fields;

}


function get_column_joins($source, $columns, $info, $joinlist) {
    $joins = array();
    foreach($columns as $column) {
        $type = isset($column['type']) ? $column['type'] : '';

        if(array_key_exists($type, $info)) {
            $value = isset($column['value']) ? $column['value'] : '';

            if(array_key_exists($value, $info[$type])) {

                foreach ($info[$type][$value]['joins'] as $join) {
                    if(array_key_exists($join, $joinlist)) {
                        // add any joins that are required to an array of joins
                        // because we are storing in associative array, each join
                        // is only stored once (as required)
                        $joins[$join] = $joinlist[$join];
                    } else {
                        trigger_error("get_column_joins(): join name $join not in joinlist",E_USER_ERROR);
                    }
                }
            } else {
                trigger_error("get_column_joins(): column value '$value' not found in source '$source' for type '$type'",E_USER_ERROR);
            }
        } else {
            trigger_error("get_column_joins(): column type '$type' not found in source '$source'",E_USER_ERROR);
        }
    }
    return $joins;

}

function get_filter_joins($source, $info, $joinlist) {
    global $SESSION;
    $joins = array();
    // check session variable for any active filters
    // if they exist we need to make sure we have included joins for them too
    $filtername = 'filtering_'.$source;
    if ($SESSION->$filtername) {
        foreach ($SESSION->$filtername as $filter => $unused) {
            // parse the filtername for type and value
            $parts = explode('-',$filter);
            if (count($parts) != 2) {
                trigger_error("get_filter_joins(): filter name format incorrect. Query snippets may have included a dash character.", E_USER_WARNING);
                continue;
            }
            $type = $parts[0];
            $value = $parts[1];
            foreach($info[$type][$value]['joins'] as $join) {
                if(array_key_exists($join, $joinlist)) {
                    $joins[$join] = $joinlist[$join];
                } else {
                    trigger_error("get_filter_joins(): join name $join not in joinlist",E_USER_ERROR);
                }
            }
        }
    }
    return $joins;

}


// sort function for uksort()
function sort_join($el1, $el2) {
    // can't find a way to pass arguments to callback function
    // so using global var :-(
    global $joinlist;
    // order of this array determines order of joins
    // earlier elements joined first
    $order = array_keys($joinlist);

    $el1key = array_search($el1, $order);
    $el2key = array_search($el2, $order);

    // determine sort order
    // if key is missing, put at the end
    if($el1key !== false && $el2key === false) {
        trigger_error("Missing array key in sort_join(). Add '$el2' to order array.",E_USER_WARNING);
        return -1;
    } else if ($el2key !== false && $el1key === false) {
        trigger_error("Missing array key in sort_join(). Add '$el1' to order array.",E_USER_WARNING);
        return 1;
    } else if ($el1key === false && $el2key === false) {
        trigger_error("Missing array keys in sort_join(). Add '$el1' and '$el2' to order array.",E_USER_WARNING);
        return 0;
    } else if($el1key < $el2key) {
        return -1;
    } else if($el1key > $el2key) {
        return 1;
    } else {
        return 0;
    }
}


