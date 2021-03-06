<?php

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/totara/cohort/lib.php');
require_once($CFG->libdir. '/coursecatlib.php');

/**
 * The form for handling editing a course.
 */
class course_edit_form extends moodleform {
    protected $course;
    protected $context;

    /**
     * Form definition.
     */
    function definition() {
        global $USER, $CFG, $DB, $PAGE, $TOTARA_COURSE_TYPES, $COHORT_VISIBILITY;

        $mform    = $this->_form;
        $PAGE->requires->yui_module('moodle-course-formatchooser', 'M.course.init_formatchooser',
                array(array('formid' => $mform->getAttribute('id'))));

        $course        = $this->_customdata['course']; // this contains the data of this form
        $category      = $this->_customdata['category'];
        $editoroptions = $this->_customdata['editoroptions'];
        $returnto = $this->_customdata['returnto'];
        $nojs = (isset($this->_customdata['nojs'])) ? $this->_customdata['nojs'] : 0 ;

        $systemcontext   = context_system::instance();
        $categorycontext = context_coursecat::instance($category->id);

        if (!empty($course->id)) {
            $coursecontext = context_course::instance($course->id);
            $context = $coursecontext;
        } else {
            $coursecontext = null;
            $context = $categorycontext;
        }

        $courseconfig = get_config('moodlecourse');

        $this->course  = $course;
        $this->context = $context;

        // Form definition with new course defaults.
        $mform->addElement('header','general', get_string('general', 'form'));

        $mform->addElement('hidden', 'returnto', null);
        $mform->setType('returnto', PARAM_ALPHANUM);
        $mform->setConstant('returnto', $returnto);
        $attributes = array();
        $attributes['class'] = 'totara-limited-width';
        $attributes['onchange'] = 'if (document.all) { this.className=\'totara-limited-width\';}';
        $attributes['onmousedown'] = 'if (document.all) this.className=\'totara-expanded-width\';';
        $attributes['onblur'] = 'if (document.all) this.className=\'totara-limited-width\';';

        $mform->addElement('text','fullname', get_string('fullnamecourse'),'maxlength="254" size="50"');
        $mform->addHelpButton('fullname', 'fullnamecourse');
        $mform->addRule('fullname', get_string('missingfullname'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_TEXT);
        if (!empty($course->id) and !has_capability('moodle/course:changefullname', $coursecontext)) {
            $mform->hardFreeze('fullname');
            $mform->setConstant('fullname', $course->fullname);
        }

        $mform->addElement('text', 'shortname', get_string('shortnamecourse'), 'maxlength="100" size="20"');
        $mform->addHelpButton('shortname', 'shortnamecourse');
        $mform->addRule('shortname', get_string('missingshortname'), 'required', null, 'client');
        $mform->setType('shortname', PARAM_TEXT);
        if (!empty($course->id) and !has_capability('moodle/course:changeshortname', $coursecontext)) {
            $mform->hardFreeze('shortname');
            $mform->setConstant('shortname', $course->shortname);
        }

        // Verify permissions to change course category or keep current.
        if (empty($course->id)) {
            if (has_capability('moodle/course:create', $categorycontext)) {
                $displaylist = coursecat::make_categories_list('moodle/course:create');
                $mform->addElement('select', 'category', get_string('coursecategory'), $displaylist);
                $mform->addHelpButton('category', 'coursecategory');
                $mform->setDefault('category', $category->id);
            } else {
                $mform->addElement('hidden', 'category', null);
                $mform->setType('category', PARAM_INT);
                $mform->setConstant('category', $category->id);
            }
        } else {
            if (has_capability('moodle/course:changecategory', $coursecontext)) {
                $displaylist = coursecat::make_categories_list('moodle/course:create');
                if (!isset($displaylist[$course->category])) {
                    //always keep current
                    $displaylist[$course->category] = coursecat::get($course->category, MUST_EXIST, true)->get_formatted_name();
                }
                $mform->addElement('select', 'category', get_string('coursecategory'), $displaylist);
                $mform->addHelpButton('category', 'coursecategory');
            } else {
                //keep current
                $mform->addElement('hidden', 'category', null);
                $mform->setType('category', PARAM_INT);
                $mform->setConstant('category', $course->category);
            }
        }

        if (empty($CFG->audiencevisibility)) {
            $choices = array();
            $choices['0'] = get_string('hide');
            $choices['1'] = get_string('show');
            $mform->addElement('select', 'visible', get_string('visible'), $choices);
            $mform->addHelpButton('visible', 'visible');
            $mform->setDefault('visible', $courseconfig->visible);
            if (!empty($course->id)) {
                if (!has_capability('moodle/course:visibility', $coursecontext)) {
                    $mform->hardFreeze('visible');
                    $mform->setConstant('visible', $course->visible);
                } else {
                    if (!guess_if_creator_will_have_course_capability('moodle/course:visibility', $categorycontext)) {
                        $mform->hardFreeze('visible');
                        $mform->setConstant('visible', $courseconfig->visible);
                    }
                }
            }
        }
        //Course type
        $coursetypeoptions = array();
        foreach($TOTARA_COURSE_TYPES as $k => $v) {
            $coursetypeoptions[$v] = get_string($k, 'totara_core');
        }
        $mform->addElement('select', 'coursetype', get_string('coursetype', 'totara_core'), $coursetypeoptions);

        $mform->addElement('date_selector', 'startdate', get_string('startdate'));
        $mform->addHelpButton('startdate', 'startdate');
        $mform->setDefault('startdate', time() + 3600 * 24);

        $mform->addElement('text','idnumber', get_string('idnumbercourse'),'maxlength="100"  size="10"');
        $mform->addHelpButton('idnumber', 'idnumbercourse');
        $mform->setType('idnumber', PARAM_RAW);
        if (!empty($course->id) and !has_capability('moodle/course:changeidnumber', $coursecontext)) {
            $mform->hardFreeze('idnumber');
            $mform->setConstants('idnumber', $course->idnumber);
        }

        // Description.
        $mform->addElement('header', 'descriptionhdr', get_string('description'));
        $mform->setExpanded('descriptionhdr');

        $mform->addElement('editor','summary_editor', get_string('coursesummary'), null, $editoroptions);
        $mform->addHelpButton('summary_editor', 'coursesummary');
        $mform->setType('summary_editor', PARAM_RAW);
        $summaryfields = 'summary_editor';

        if ($overviewfilesoptions = course_overviewfiles_options($course)) {
            $mform->addElement('filemanager', 'overviewfiles_filemanager', get_string('courseoverviewfiles'), null, $overviewfilesoptions);
            $mform->addHelpButton('overviewfiles_filemanager', 'courseoverviewfiles');
            $summaryfields .= ',overviewfiles_filemanager';
        }

        if (!empty($course->id) and !has_capability('moodle/course:changesummary', $coursecontext)) {
            // Remove the description header it does not contain anything any more.
            $mform->removeElement('descriptionhdr');
            $mform->hardFreeze($summaryfields);
        }

        // Course format.
        $mform->addElement('header', 'courseformathdr', get_string('type_format', 'plugin'));

        $courseformats = get_sorted_course_formats(true);
        $formcourseformats = array();
        foreach ($courseformats as $courseformat) {
            $formcourseformats[$courseformat] = get_string('pluginname', "format_$courseformat");
        }
        if (isset($course->format)) {
            $course->format = course_get_format($course)->get_format(); // replace with default if not found
            if (!in_array($course->format, $courseformats)) {
                // this format is disabled. Still display it in the dropdown
                $formcourseformats[$course->format] = get_string('withdisablednote', 'moodle',
                        get_string('pluginname', 'format_'.$course->format));
            }
        }

        $mform->addElement('select', 'format', get_string('format'), $formcourseformats);
        $mform->addHelpButton('format', 'format');
        $mform->setDefault('format', $courseconfig->format);

        // Button to update format-specific options on format change (will be hidden by JavaScript).
        $mform->registerNoSubmitButton('updatecourseformat');
        $mform->addElement('submit', 'updatecourseformat', get_string('courseformatudpate'));

        // Just a placeholder for the course format options.
        $mform->addElement('hidden', 'addcourseformatoptionshere');
        $mform->setType('addcourseformatoptionshere', PARAM_BOOL);

        // Appearance.
        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        if (!empty($CFG->allowcoursethemes)) {
            $themeobjects = get_list_of_themes();
            $themes=array();
            $themes[''] = get_string('forceno');
            foreach ($themeobjects as $key=>$theme) {
                if (empty($theme->hidefromselector)) {
                    $themes[$key] = get_string('pluginname', 'theme_'.$theme->name);
                }
            }
            $mform->addElement('select', 'theme', get_string('forcetheme'), $themes);
        }

        $languages=array();
        $languages[''] = get_string('forceno');
        $languages += get_string_manager()->get_list_of_translations();
        $mform->addElement('select', 'lang', get_string('forcelanguage'), $languages);
        $mform->setDefault('lang', $courseconfig->lang);

        // Multi-Calendar Support - see MDL-18375.
        $calendartypes = \core_calendar\type_factory::get_list_of_calendar_types();
        // We do not want to show this option unless there is more than one calendar type to display.
        if (count($calendartypes) > 1) {
            $calendars = array();
            $calendars[''] = get_string('forceno');
            $calendars += $calendartypes;
            $mform->addElement('select', 'calendartype', get_string('forcecalendartype', 'calendar'), $calendars);
        }

        $options = range(0, 10);
        $mform->addElement('select', 'newsitems', get_string('newsitemsnumber'), $options);
        $mform->addHelpButton('newsitems', 'newsitemsnumber');
        $mform->setDefault('newsitems', $courseconfig->newsitems);

        $mform->addElement('selectyesno', 'showgrades', get_string('showgrades'));
        $mform->addHelpButton('showgrades', 'showgrades');
        $mform->setDefault('showgrades', $courseconfig->showgrades);

        $mform->addElement('selectyesno', 'showreports', get_string('showreports'));
        $mform->addHelpButton('showreports', 'showreports');
        $mform->setDefault('showreports', $courseconfig->showreports);

        // Files and uploads.
        $mform->addElement('header', 'filehdr', get_string('filesanduploads'));

        if (!empty($course->legacyfiles) or !empty($CFG->legacyfilesinnewcourses)) {
            if (empty($course->legacyfiles)) {
                //0 or missing means no legacy files ever used in this course - new course or nobody turned on legacy files yet
                $choices = array('0'=>get_string('no'), '2'=>get_string('yes'));
            } else {
                $choices = array('1'=>get_string('no'), '2'=>get_string('yes'));
            }
            $mform->addElement('select', 'legacyfiles', get_string('courselegacyfiles'), $choices);
            $mform->addHelpButton('legacyfiles', 'courselegacyfiles');
            if (!isset($courseconfig->legacyfiles)) {
                // in case this was not initialised properly due to switching of $CFG->legacyfilesinnewcourses
                $courseconfig->legacyfiles = 0;
            }
            $mform->setDefault('legacyfiles', $courseconfig->legacyfiles);
        }

        // Handle non-existing $course->maxbytes on course creation.
        $coursemaxbytes = !isset($course->maxbytes) ? null : $course->maxbytes;

        // Let's prepare the maxbytes popup.
        $choices = get_max_upload_sizes($CFG->maxbytes, 0, 0, $coursemaxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maximumupload'), $choices);
        $mform->addHelpButton('maxbytes', 'maximumupload');
        $mform->setDefault('maxbytes', $courseconfig->maxbytes);

        // Completion tracking.
        if (completion_info::is_enabled_for_site()) {
            $mform->addElement('header', 'completionhdr', get_string('completion', 'completion'));
            $mform->addElement('selectyesno', 'enablecompletion', get_string('enablecompletion', 'completion'));
            $mform->setDefault('enablecompletion', $courseconfig->enablecompletion);
            $mform->addHelpButton('enablecompletion', 'enablecompletion', 'completion');

            $mform->addElement('advcheckbox', 'completionstartonenrol', get_string('completionstartonenrol', 'completion'));
            $mform->setDefault('completionstartonenrol', $courseconfig->completionstartonenrol);
            $mform->disabledIf('completionstartonenrol', 'enablecompletion', 'eq', 0);

            $mform->addElement('advcheckbox', 'completionprogressonview', get_string('completionprogressonview', 'completion'));
            $mform->setDefault('completionprogressonview', $courseconfig->completionprogressonview);
            $mform->disabledIf('completionprogressonview', 'enablecompletion', 'eq', 0);
            $mform->addHelpButton('completionprogressonview', 'completionprogressonview', 'completion');
        } else {
            $mform->addElement('hidden', 'enablecompletion');
            $mform->setType('enablecompletion', PARAM_INT);
            $mform->setDefault('enablecompletion', 0);

            $mform->addElement('hidden', 'completionstartonenrol');
            $mform->setType('completionstartonenrol', PARAM_INT);
            $mform->setDefault('completionstartonenrol',0);

            $mform->addElement('hidden', 'completionprogressonview');
            $mform->setType('completionprogressonview', PARAM_INT);
            $mform->setDefault('completionprogressonview', 0);
        }

        // Course Icons
        if (empty($course->id)) {
            $action = 'add';
        } else {
            $action = 'edit';
        }
        $course->icon = isset($course->icon) ? $course->icon : 'default';
        totara_add_icon_picker($mform, $action, 'course', $course->icon, $nojs);
        // END Course Icons

//--------------------------------------------------------------------------------
        enrol_course_edit_form($mform, $course, $context);

        //only show the Enrolled Audiences functionality to users with the appropriate permissions
        if (has_capability('moodle/cohort:manage', $systemcontext)) {
            $mform->addElement('header','enrolledcohortshdr', get_string('enrolledcohorts', 'totara_cohort'));

            if (empty($course->id)) {
                $cohorts = '';
            } else {
                $cohorts = totara_cohort_get_course_cohorts($course->id, null, 'c.id');
                $cohorts = !empty($cohorts) ? implode(',', array_keys($cohorts)) : '';
            }

            $mform->addElement('hidden', 'cohortsenrolled', $cohorts);
            $mform->setType('cohortsenrolled', PARAM_SEQUENCE);
            $cohortsclass = new totara_cohort_course_cohorts(COHORT_ASSN_VALUE_ENROLLED);
            $cohortsclass->build_table(!empty($course->id) ? $course->id : 0);
            $mform->addElement('html', $cohortsclass->display(true));

            $mform->addElement('button', 'cohortsaddenrolled', get_string('cohortsaddenrolled', 'totara_cohort'));
            $mform->setExpanded('enrolledcohortshdr');
        }

        // Only show the Audiences Visibility functionality to users with the appropriate permissions.
        if (!empty($CFG->audiencevisibility) && has_capability('totara/coursecatalog:manageaudiencevisibility', $systemcontext)) {
            $mform->addElement('header', 'visiblecohortshdr', get_string('audiencevisibility', 'totara_cohort'));
            $mform->addElement('select', 'audiencevisible', get_string('visibility', 'totara_cohort'), $COHORT_VISIBILITY);
            $mform->addHelpButton('audiencevisible', 'visiblelearning', 'totara_cohort');

            if (empty($course->id)) {
                $mform->setDefault('audiencevisible', $courseconfig->visiblelearning);
                $cohorts = '';
            } else {
                $cohorts = totara_cohort_get_visible_learning($course->id);
                $cohorts = !empty($cohorts) ? implode(',', array_keys($cohorts)) : '';
            }

            $mform->addElement('hidden', 'cohortsvisible', $cohorts);
            $mform->setType('cohortsvisible', PARAM_SEQUENCE);
            $cohortsclass = new totara_cohort_visible_learning_cohorts();
            $instanceid = !empty($course->id) ? $course->id : 0;
            $instancetype = COHORT_ASSN_ITEMTYPE_COURSE;
            $cohortsclass->build_visible_learning_table($instanceid, $instancetype);
            $mform->addElement('html', $cohortsclass->display(true, 'visible'));

            $mform->addElement('button', 'cohortsaddvisible', get_string('cohortsaddvisible', 'totara_cohort'));
            $mform->setExpanded('visiblecohortshdr');
        }

//--------------------------------------------------------------------------------
        $mform->addElement('header','groups', get_string('groupsettingsheader', 'group'));

        $choices = array();
        $choices[NOGROUPS] = get_string('groupsnone', 'group');
        $choices[SEPARATEGROUPS] = get_string('groupsseparate', 'group');
        $choices[VISIBLEGROUPS] = get_string('groupsvisible', 'group');
        $mform->addElement('select', 'groupmode', get_string('groupmode', 'group'), $choices);
        $mform->addHelpButton('groupmode', 'groupmode', 'group');
        $mform->setDefault('groupmode', $courseconfig->groupmode);

        $mform->addElement('selectyesno', 'groupmodeforce', get_string('groupmodeforce', 'group'));
        $mform->addHelpButton('groupmodeforce', 'groupmodeforce', 'group');
        $mform->setDefault('groupmodeforce', $courseconfig->groupmodeforce);

        //default groupings selector
        $options = array();
        $options[0] = get_string('none');
        $mform->addElement('select', 'defaultgroupingid', get_string('defaultgrouping', 'group'), $options);

        // Customizable role names in this course.
        $mform->addElement('header','rolerenaming', get_string('rolerenaming'));
        $mform->addHelpButton('rolerenaming', 'rolerenaming');

        if ($roles = get_all_roles()) {
            $roles = role_fix_names($roles, null, ROLENAME_ORIGINAL);
            $assignableroles = get_roles_for_contextlevels(CONTEXT_COURSE);
            foreach ($roles as $role) {
                $mform->addElement('text', 'role_'.$role->id, get_string('yourwordforx', '', $role->localname));
                $mform->setType('role_'.$role->id, PARAM_TEXT);
            }
        }
//--------------------------------------------------------------------------------
        if (empty($course->id)) {
            $course->id = 0;
        }
        customfield_definition($mform, $course, 'course', 0, 'course');

//--------------------------------------------------------------------------------

//--------------------------------------------------------------------------------
// Display offical Course Tags
        if (!empty($CFG->usetags) && $DB->count_records('tag', array('tagtype' => 'official')) && (get_config('moodlecourse', 'coursetagging') == 1)) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));

            $namefield = empty($CFG->keeptagnamecase) ? 'name' : 'rawname';
            $sql = "SELECT id, {$namefield} FROM {tag} WHERE tagtype = ? ORDER by name ASC";
            $params = array('official');
            if ($otags = $DB->get_records_sql_menu($sql, $params)) {
                $otagsselEl =& $mform->addElement('select', 'otags', get_string('otags', 'tag'), $otags, 'size="5"');
                $otagsselEl->setMultiple(true);
                $mform->addHelpButton('otags', 'otags', 'tag');
            }
        }

        $this->add_action_buttons();

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        // Finally set the current form data
        $this->set_data($course);
    }

    /**
     * Fill in the current page data for this course.
     */
    function definition_after_data() {
        global $DB;

        $mform = $this->_form;

        // add available groupings
        if ($courseid = $mform->getElementValue('id') and $mform->elementExists('defaultgroupingid')) {
            $options = array();
            if ($groupings = $DB->get_records('groupings', array('courseid'=>$courseid))) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = format_string($grouping->name);
                }
            }
            core_collator::asort($options);
            $gr_el =& $mform->getElement('defaultgroupingid');
            $gr_el->load($options);
        }

        $courseid = $mform->getElementValue('id') ? $mform->getElementValue('id') : 0;
        if ($course = $DB->get_record('course', array('id' => $courseid))) {
            customfield_definition_after_data($mform, $course, 'course', 0, 'course');
        }

        // add course format options
        $formatvalue = $mform->getElementValue('format');
        if (is_array($formatvalue) && !empty($formatvalue)) {
            $courseformat = course_get_format((object)array('format' => $formatvalue[0]));

            $elements = $courseformat->create_edit_form_elements($mform);
            for ($i = 0; $i < count($elements); $i++) {
                $mform->insertElementBefore($mform->removeElement($elements[$i]->getName(), false),
                        'addcourseformatoptionshere');
            }
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        // Add field validation check for duplicate shortname.
        if ($course = $DB->get_record('course', array('shortname' => $data['shortname']), '*', IGNORE_MULTIPLE)) {
            if (empty($data['id']) || $course->id != $data['id']) {
                $errors['shortname'] = get_string('shortnametaken', '', $course->fullname);
            }
        }

        // Add field validation check for duplicate idnumber.
        if (!empty($data['idnumber']) && (empty($data['id']) || $this->course->idnumber != $data['idnumber'])) {
            if ($course = $DB->get_record('course', array('idnumber' => $data['idnumber']), '*', IGNORE_MULTIPLE)) {
                if (empty($data['id']) || $course->id != $data['id']) {
                    $errors['idnumber'] = get_string('courseidnumbertaken', 'error', $course->fullname);
                }
            }
        }

        $errors += customfield_validation((object)$data, 'course', 'course');

        $errors = array_merge($errors, enrol_course_edit_validation($data, $this->context));

        $courseformat = course_get_format((object)array('format' => $data['format']));
        $formaterrors = $courseformat->edit_form_validation($data, $files, $errors);
        if (!empty($formaterrors) && is_array($formaterrors)) {
            $errors = array_merge($errors, $formaterrors);
        }

        return $errors;
    }
}

