<?php

/**
 * Class Datagrid
 *
 * @author Pqbao
 * @copyright Kobe Digital Labo, Inc
 * @since 11/04/2014
 */
class Application_Model_Datagrid {

    protected $_data = NULL; //---array;
    protected $header = NULL; //---array;
    protected $position_page = 1;
    protected $option_list = 1;
    protected $action_delete = false;
    protected $action_select_all = false;
    protected $action_update = false;
    protected $action_idkey = '';
    protected $reset_no = false;
    protected $sumnumrow = ''; //---total rows
    protected $_url = '';

    public function __construct($data = NULL, $header = NULL, $position_page = 1, $option = '', $reset_no = false) {

        $req = Zend_Controller_Front::getInstance()->getRequest();

        //---get Params from URL
        $params = $req->getParams();
        $position_page = isset($params['page']) ? $params['page'] : 1;

        //---get controllert name;
        $this->controller = $req->getControllerName();
        //---get action name
        $this->action = $req->getActionName();

        //---create URL this page;
        $url = '/' . $this->controller . '/' . $this->action;
        $url = $this->getCreateURL($url, $params);
        $this->position_page = $position_page;
        $this->option_list = $option;
        $this->action_select_all = (is_array($option) && isset($option['select_all'])) ? true : false;

        $this->_url = $url;

        //---get data list from json
        $this->_data = isset($data['rows']) ? $data['rows'] : array();

        //---reset No
        if (!(is_null($reset_no))) {
            $this->reset_no = $reset_no;
        }
        //---get data header from header
        $this->header = $header;

        //---get total of list data  from json
        $this->sumnumrow = isset($data['total']) ? $data['total'] : 0;
    }

    /*
     * searchList
     * create form search
     * @$filterKey array
     * return string html
     */

    public function searchList($filterKey) {
        $html = '';
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $params = $req->getParams();

        $html .= '<div id="filter">
                    <div>
                            <div class="lft">検索条件</div>					
                            <div class="lft"><input id="display-search" onChange="displaySearch();" type="checkbox" checked /></div>
                            <div class="lft">表示切替</div>
                            <div class="clr">&nbsp;</div>
                    </div>
                    <div id="searchForm">
                            <table class="data-filter" border="1">					
                                    ';
        $stt = 0;
        foreach ($filterKey as $key => $value) {
            $df_value = isset($params[$key]) ? $params[$key] : $value['value'];
            $class = 'even';
            if ($stt % 2 == 0) {
                $class = 'odd';
            }
            $stt++;
            $html .= '				
                                    <tr class="' . $class . '">
                                            <td class="label" width="100px">' . $value['title'] . '</td>
                                            <td width="220px"><input name="' . $key . '" id="' . $key . '" class="name" type="text" value="' . $df_value . '" /></td>
                                    </tr>
                    ';
        }
        $html .= '
                            </table>
                                                    <div class="btn-search">
                                                            <input class="input-btn" name="btnSearch" id="btnSearch" onlcick="searchData()" type="button"  value="検索" />
                                                    </div>
                                            </div>
                                </div>
                                <div class="clr">&nbsp;</div>
                                <script>
                                    if($("#display-search").is(":checked")){
                                        $("#searchForm").show();
                                    }else{
                                        $("#searchForm").hide();
                                    }
                                    function displaySearch(){
                                            if($("#display-search").is(":checked")){
                                                $("#searchForm").show();
                                            }else{
                                                $("#searchForm").hide();
                                            }
                                    }

                                </script>	';
        return $html;
    }

    /*
     * creatPageNavigation
     * create combo select num rows page
     * @$pagenavigation array
     * @$numrow num
     * return string html
     */

    public function creatPageNavigation($pagenavigation = NULL, $numrow = 20) {
        if (Globals::isMobile()) {
            return $this->creatPageNavigationMobile($pagenavigation, $numrow);
        }
        $req = Zend_Controller_Front::getInstance()->getRequest();

        //---get params
        $params = $req->getParams();

        //---get option pagelist from config aplication.ini
        $menuConfig = Globals::getApplicationConfig('optlist');
        if (is_null($pagenavigation)) {
            $pagenavigation = explode(',', $menuConfig->list_page_navigation);
        }

        $html = '';
        $html .= '<div class="detail-btn" style="float: right">
                            <span class="rt">表示件数：</span>
                            <select class="short-select" id="rows" name="rows">';

        //---get session data
        $session = Globals::getSession();
        if (isset($params['rows'])) {
            $session->view_count_list = $params['rows'];
        }
        if (isset($session->view_count_list)) {
            $numrow = $session->view_count_list;
        } else {
            $numrow = $menuConfig->list_count; //-- get file in app.ini
            $session->view_count_list = $numrow;
        }

        //---create list num row on page.
        foreach ($pagenavigation as $key => $value) {
            $title = $value . '件';
            if ($value == 'ALL') {
                $value = 'all';
                $title = '全て';
            }
            if ($numrow == strtolower($value)) {
                $html .= '<option selected="selected" value="' . $value . '">' . $title . '</option>';
            } else {
                $html .= '<option value="' . $value . '">' . $title . '</option>';
            }
        }
        $html .= '</select></div>';
        return $html;
    }
    
    private function creatPageNavigationMobile($pagenavigation = NULL, $numrow = 20) {
        $req = Zend_Controller_Front::getInstance()->getRequest();

        //---get params
        $params = $req->getParams();

        //---get option pagelist from config aplication.ini
        $menuConfig = Globals::getApplicationConfig('optlist');
        if (is_null($pagenavigation)) {
            $pagenavigation = explode(',', $menuConfig->list_page_navigation);
        }
        
        $data = '<select class="short-select" id="rows" name="rows">';
        //---get session data
        $session = Globals::getSession();
        if (isset($params['rows'])) {
            $session->view_count_list = $params['rows'];
        }
        if (isset($session->view_count_list)) {
            $numrow = $session->view_count_list;
        } else {
            $numrow = $menuConfig->list_count; //-- get file in app.ini
            $session->view_count_list = $numrow;
        }

        //---create list num row on page.
        foreach ($pagenavigation as $key => $value) {
            $title = $value . '件';
            if ($value == 'ALL') {
                $value = 'all';
                $title = '全て';
            }
            if ($numrow == strtolower($value)) {
                $data .= '<option selected="selected" value="' . $value . '">' . $title . '</option>';
            } else {
                $data .= '<option value="' . $value . '">' . $title . '</option>';
            }
        }
        $data .= '</select>';
        
        return '<table width="100%" border="1" class="data-filter"><tbody><tr class="odd">
            <td width="20%" class="label">表示件数</td>
            <td>' . $data . '</td></tr></tbody></table>';
    }

    /*
     * creatPageLink
     * create link page
     * @$numrow num
     * return string html
     */

    public function creatPageLink($numrow = 20) {
        $isMobile = Globals::isMobile();
        //--- get max link page on list
        $menuConfig = Globals::getApplicationConfig('optlist');
        $count_link_page = $menuConfig->link_page_count;
        if ($isMobile) {
            $count_link_page = $menuConfig->link_page_count_mobile;
        }

        //---between 2 page;
        $between_link_page = ((int) ($count_link_page / 2)) + 1;

        //---get session data
        $session = Globals::getSession();
        if (isset($session->view_count_list)) {
            $numrow = $session->view_count_list; //---num row on page
        }

        //---total row
        $sumNumRow = $this->sumnumrow;

        //---get total page.
        if ($numrow > 0) {
            $sumPage = (int) ($sumNumRow / $numrow);
            if ($sumNumRow % $numrow > 0) {
                $sumPage = $sumPage + 1;
            }
        } else {
            $sumPage = 1;
        }


        if ($sumPage < $this->position_page) {
            $this->position_page = 1;
        }

        $html_paging = '<div class="pager">';
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $params = $req->getParams();

        unset($params['page']); //--remove page;
        $this->controller = $req->getControllerName();
        $this->action = $req->getActionName();
        $url = $this->getCreateURL('/' . $this->controller . '/' . $this->action, $params);

        //---page previous.
        if ($isMobile) {
            $html_paging .= '<div class="left-box">';
        }
        if ($this->position_page > 1) {
            $html_paging .= '<span class="pager-next"><a rel="next" href="' . $url . '/page/' . ($this->position_page - 1) . '"> &lt;</a></span>';
        } else if ($isMobile) {
            $html_paging .= '<span class="current">&lt;</span>';
        }

        if ($isMobile) {
            $html_paging .= '</div>';
            $html_paging .= '<div class="center-box">';
        }
        //---page first 1
        if ($this->position_page > $between_link_page && $sumPage > $count_link_page) {
            $html_paging .= '<span ><a href="' . $url . '/page/' . 1 . '">' . 1 . '</a></span>';
            $html_paging .= '<span class="  pager-texter-doct ">…</span>';
        }
        $start = ($this->position_page < $between_link_page ) ? 1 : ( $this->position_page - $between_link_page + 1);
        $end = ($this->position_page < $between_link_page ) ? ($count_link_page) : ( $count_link_page + $this->position_page - $between_link_page );
        if ($start < 1 || $count_link_page > $sumPage) {
            $start = 1;
        }
        if ($end > $sumPage) {
            $end = $sumPage;
        }

        if (( $count_link_page + $this->position_page - $between_link_page ) > $sumPage && $start > 1 && $sumPage > $count_link_page) {
            $start = $start - (( $count_link_page + $this->position_page - $between_link_page ) - $sumPage);
        }
        for ($index = $start; $index <= $end; $index++) {
            if ($this->position_page == $index) {
                $html_paging .= '<span class="current">' . $index . '</span>';
            } else {
                $html_paging .= '<span ><a href="' . $url . '/page/' . $index . '">' . $index . '</a></span>';
            }
        }
        //---page last end;
        if (($count_link_page + $this->position_page - $between_link_page) < ($sumPage) && $sumPage > $count_link_page) {
            $html_paging .= '<span class="  pager-texter-doct">…</span>';
            $html_paging .= '<span ><a href="' . $url . '/page/' . $sumPage . '">' . $sumPage . '</a></span>';
        }
        if ($isMobile) {
            $html_paging .= '</div>';
        }
        //---page next
        if ($this->position_page < $sumPage) {
            if ($isMobile) {
                $html_paging .= '<div class="right-box"><span class="pager-next"><a rel="next" href="' . $url . '/page/' . ($this->position_page + 1) . '"> &gt;</a></span></div>';
            } else {
                $html_paging .= '<span class="pager-next"><a rel="next" href="' . $url . '/page/' . ($this->position_page + 1) . '"> &gt;</a></span>';
            }
        } else {
            if ($isMobile) {
                $html_paging .= '<div class="right-box"><span class="current">&gt;</span></div>';
            }
        }

        $html_paging .= '</div>';
        $html_desc = '';
        if ($isMobile) {
            $html_desc = '<div class="pager-counter">
                        <p>' . ($this->position_page ) . '/' . $sumPage . 'ページ, ' . $sumNumRow . '件中, ' . ($this->position_page * $numrow - $numrow + 1) . '-' . (($this->position_page * $numrow > $sumNumRow ) ? $sumNumRow : $this->position_page * $numrow) . '件目を表示</p>
                                </div>';
        } else {
            $html_desc = '<div class="pager-counter">
                        <p> ' . ($this->position_page ) . ' / ' . $sumPage . ' ページ, ' . $sumNumRow . ' 件中, ' . ($this->position_page * $numrow - $numrow + 1) . ' - ' . (($this->position_page * $numrow > $sumNumRow ) ? $sumNumRow : $this->position_page * $numrow) . ' 件目を表示 </p>
                                </div>';
        }
        
        $html = '<div class="pager-section">';
        if (Globals::isMobile()) {
            $html .= $html_desc . $html_paging . '</div>';
        } else {
            $html .= $html_paging . $html_desc . '</div>';
        }
        
        if ($sumNumRow <= 0) {
            if (Globals::isMobile()) {
                $html = '<div class="pager-section">                                        
                                        <div class="pager-counter">
                        <p> &nbsp;</p>
                                        </div>
                                        <div class="pager">
                                        </div>
                                    </div>';
            } else {
                $html = '<div class="pager-section">
                                        <div class="pager">
                                        </div>
                                        <div class="pager-counter">
                        <p> &nbsp;</p>
                                        </div>
                                    </div>';
            }
        } else if ($sumPage <= 1) {
            if (Globals::isMobile()) {
                $html = '<div class="pager-section">                                        
                                        <div class="pager-counter">
                        <p>1/1ページ, ' . $sumNumRow . '件中, 1-' . $sumNumRow . '件目を表示</p>
                                        </div>
                                        <div class="pager">
                                            <div class="left-box"><span class="current">&lt;</span></div>
                                            <div class="center-box"><span class="current">1</span></div>
                                            <div class="right-box"><span class="current">&gt;</span></div>
                                        </div>
                                    </div>';

            } else {
                $html = '<div class="pager-section">
                                        <div class="pager">
                                        </div>
                                        <div class="pager-counter">
                        <p> 1 / 1 ページ, ' . $sumNumRow . ' 件中, 1 - ' . $sumNumRow . ' 件目を表示 </p>
                                        </div>
                                    </div>';
            }
        }

        //--add hidden sort, order;
        $hidden_page = '';
        if (isset($params['sort'])) {
            $hidden_page .= '<input type="hidden" id="sort" name="sort" value="' . $params['sort'] . '" />';
        }
        if (isset($params['order'])) {
            $hidden_page .= '<input type="hidden" id="order" name="order" value="' . $params['order'] . '" />';
        }

        return $html . '<div class="clr">&nbsp;' . $hidden_page . '</div>';
    }

    /*
     * createList
     * create list data=> header, body
     * $arrAction array
     * $options array
     * $hiddenName array
     * return string html
     */

    public function createList($arrAction, $options = NULL, $hiddenName = NULL) {
        $html = '';
        $req = Zend_Controller_Front::getInstance()->getRequest();

        //---get params
        $params = $req->getParams();
        $this->controller = $req->getControllerName(); //---get controller name
        $this->action = $req->getActionName(); //--- get action name
        $url = '/' . $this->controller . '/' . $this->action;

        $option_col = array();

        $html .= '<div id="data-grid">';
        $html .= '<table class="grid" cellpadding="0" cellspacing="0" border="1" width="100%" ' . (is_string($this->option_list) ? $this->option_list : '') . '>';

        $colspan = 1; //---col span when data null
        //---create header row
        $header_html = '';
        if ($this->action_select_all) {
            $header_html .= '<th style="width:45px;padding:8px;" class="count">' . $this->option_list['select_all']['title'] . '<br/><input type="checkbox" name="' . $this->option_list['select_all']['check_row_name'] . '" id="' . $this->option_list['select_all']['check_row_name'] . '" child="' . $this->option_list['select_all']['check_row_name'] . '[]" onclick="' . $this->option_list['select_all']['check_all_event'] . '"/></th>';
        } else {
            $header_html = '<th style="width:30px;padding:8px;" class="count">&nbsp;</th>';
        }
        if ($this->action_delete) {
            $header_html .= '<th> </th>';
            $colspan++;
        }
        if ($this->action_update) {
            $header_html .= '<th>編集</th>';
            $colspan++;
        }

        foreach ($this->header as $key => $value) {
            $params_tamp = array();
            $params_tamp = $params;
            $option_col[$key] = isset($value['option']) ? $value['option'] : '';
            $option_header = isset($value['hoption']) ? $value['hoption'] : '';
            $hcol_span[$key] = isset($value['colspan']) ? $value['colspan'] : '';
            $add_control[$key] = isset($value['addcontrol']) ? $value['addcontrol'] : '';

            $colspan_header = count($hcol_span[$key]);

            if (isset($value['sort'])) {
                $params_tamp['sort'] = $value['sort'];
            }
            if (isset($value['order'])) {
                $arrayDirection = array('asc' => 'desc', 'desc' => 'asc');
                $params_tamp['order'] = (isset($params_tamp['order']) && @$params['sort'] == $key ) ? $arrayDirection[$params_tamp['order']] : $arrayDirection[$value['order']];
            }

            $title = isset($value['title']) ? ($value['title']) : '';

            $img_sort = '';
            if (isset($params['sort']) && $params['sort'] == $key) {

                if ($params['order'] == 'desc') {
                    $img_sort = '<img src="/images/sort_1.png" alt="" />';
                } else {
                    $img_sort = '<img src="/images/sort_2.png" alt="" />';
                }
            }
            if ($title != '') {
                if (isset($params_tamp['sort']) && $params_tamp['sort'] == $key) {
                    $params_tamp['page'] = 1; //go to page 1
                    $url_order = $this->getCreateURL($url, $params_tamp);
                    $header_html .= '<th ' . $option_header . ' colspan="' . $colspan_header . '"><a href="' . $url_order . '">' . $title . '</a>' . $img_sort . '</th>';
                } else {
                    $header_html .= '<th ' . $option_header . ' colspan="' . $colspan_header . '">' . $title . '</th>';
                }
            }
            $colspan++;
        }
        $html .= '<thead><tr>' . $header_html . '</tr></thead><tbody>';

        //---get session
        $session = Globals::getSession();
        $numrow = $session->view_count_list; //--num row max on page
        //---get no start
        $rowIndex = -1;
        $stt = ($this->position_page * $numrow - $numrow + 1);
        if ($this->reset_no) {
            $stt = 1;
        }
        if (is_null($hiddenName)) {
            $hiddenName = array();
        }
        //--- col remove list
        $col_remove = array();

        //---foreach row, create body
        foreach ($this->_data as $key => $value) {
            $rowIndex++;
            $body_html = '';
            //--add hidden
            $hidden_input = '';
            foreach ($hiddenName as $key1 => $va) {
                if (!is_null($hiddenName) && is_array($hiddenName) && isset($value[$key1])) {
                    $val_hidden = $value[$key1];
                    $hidden_id = isset($va['id']) ? ('id="' . $va['id'] . '_' . $stt . '"') : '';
                    $hidden_input .= '<input type="hidden" ' . $hidden_id . ' name="' . $va['name'] . '" value="' . $val_hidden . '" />';
                    break;
                }
            }


            if ($this->action_select_all) {
                $body_html .= '<td class="count" style="width:30px;">' . $hidden_input . '<input type="checkbox"' . (isset($this->option_list['select_all']['checked']) && $value[$this->option_list['select_all']['checked']] > 0 ? " checked='checked' " : '') . 'name="' . $this->option_list['select_all']['check_row_name'] . '[]" id="' . $this->option_list['select_all']['check_row_name'] . '_' . $stt . '" value="' . $val_hidden . '"/>' . '</td>';
            } else {
                $body_html .= '<td class="count" style="width:30px;">' . $hidden_input . $stt . '</td>';
            }
            //---add colum edit, delete;
            if ($this->action_delete) {
                $body_html .= '<td> ' . $value[$this->action_idkey] . '</td>';
            }
            if ($this->action_update) {
                $body_html .= '<td>' . $value[$this->action_idkey] . '</td>';
            }

            //---foreach col data
            foreach ($this->header as $k_col => $val_col) {
                $option = $option_col[$k_col];
                $addcontent = $add_control[$k_col];

                $str_val = isset($value[$k_col]) ? $value[$k_col] : '';
                if (strpos($option, "curry") > 0 && is_numeric($value[$k_col])) {
                    $str_val = self::formatMoney($value[$k_col]);
                }
                if (strpos($option, "icon") > 0) {
                    if (isset($value[$k_col]) && $value[$k_col] != '' && substr($value[$k_col], strlen($value[$k_col]) - 1, 1) != '/') {
                        $str_val = '<div id="div_image_product_' . $k_col . $stt . '" class="edit_img" style="display:inline-block;">
                                                                    <a class="fancybox-effects-a" href="' . $value[$k_col] . '">
                                                                            <img  width="100px" src="' . $value[$k_col] . '" />
                                                                    </a>
                                                            </div>';
                    } else {
                        $str_val = '';
                    }
                }
                if (strpos($option, "image-link") > 0) {
                    if (isset($value[$k_col]) && $value[$k_col] != '' && substr($value[$k_col], strlen($value[$k_col]) - 1, 1) != '/') {
                        $str_val = '<img  width="100px" src="' . $value[$k_col] . '" /> ';
                    } else {
                        $str_val = '';
                    }
                }


                if (isset($value[$k_col])) {

                    //---add col span
                    if (isset($hcol_span[$k_col]) && count($hcol_span[$k_col]) > 0 && in_array($k_col, $col_remove)) {
                        foreach ($hcol_span[$k_col] as $k => $v) {
                            $col_remove[$v] = $v;
                            $option = isset($option_col[$v]) ? $option_col[$v] : '';
                            $addcontent = isset($add_control[$v]) ? $add_control[$v] : '';
                            $str_val = $this->_data[$key][$v];
                            if (strpos($option, "curry") > 0 && is_numeric($this->_data[$key][$v])) {
                                $str_val = self::formatMoney($this->_data[$key][$v]);
                            }
                            if (strpos($option, "icon") > 0) {
                                if (isset($value[$k_col]) && $value[$k_col] != '' && substr($value[$k_col], strlen($value[$k_col]) - 1, 1) != '/') {
                                    $str_val = '<div id="div_image_product' . $v . $stt . '" class="edit_img" style="display:inline-block;">
                                                                                    <a class="fancybox-effects-a" href="' . $value[$k_col] . '">
                                                                                            <img width="100px" src="' . $value[$k_col] . '" />
                                                                                    </a>
                                                                            </div>';
                                } else {
                                    $str_val = '';
                                }
                            }

                            if (strpos($option, "image-link") > 0) {
                                if (isset($value[$k_col]) && $value[$k_col] != '' && substr($value[$k_col], strlen($value[$k_col]) - 1, 1) != '/') {
                                    $str_val = '  <img width="100px" src="' . $value[$k_col] . '" />';
                                } else {
                                    $str_val = '';
                                }
                            }

                            $body_html .= '<td ' . $option . '  field="' . $v . '">' . $str_val . ' </td>';
                            if (isset($arrAction[$v])) {
                                $params[$arrAction[$v]['namepost']] = $value[$arrAction[$v]['value']];
                                $url = $this->getCreateURL($arrAction[$v]['action'], $params);
                                $name = '';
                                if (isset($arrAction[$v]['name'])) {
                                    $name = 'name="' . $arrAction[$v]['name'] . $value[$arrAction[$v]['value']] . '"';
                                }
                                $body_html .= '<td ' . $option . ' field="' . $v . '" ><a  href="' . $url . '" ' . $name . ' >' . $str_val . '</a> ' . $addcontent . '</td>';
                            } else {
                                $body_html .= '<td ' . $option . '  field="' . $v . '">' . $str_val . ' ' . $addcontent . '</td>';
                            }
                        }
                        //---end add col span
                    } else if (!in_array($k_col, $col_remove)) {
                        //--col no span
                        if (isset($arrAction[$k_col])) {
                            $params[$arrAction[$k_col]['namepost']] = $value[$arrAction[$k_col]['value']];
                            $url = $this->getCreateURL($arrAction[$k_col]['action'], $params);
                            $name = '';
                            if (isset($arrAction[$k_col]['name'])) {
                                $name = 'name="' . $arrAction[$k_col]['name'] . $value[$arrAction[$k_col]['value']] . '"';
                            }
                            // 2015/02/19 Nishiyama Add エスケープ処理 START
                            if (strpos($option, "icon") > 0) {
	                            $body_html .= '<td ' . $option . ' field="' . $k_col . '" ><a  href="' . $url . '" ' . $name . ' >' . $str_val . '</a> ' . $addcontent . ' </td>';
	                        }else{
	                            $body_html .= '<td ' . $option . ' field="' . $k_col . '" ><a  href="' . $url . '" ' . $name . ' >' . htmlspecialchars($str_val) . '</a> ' . $addcontent . ' </td>';
	                        
	                        }
                            // 2015/02/19 Nishiyama Add エスケープ処理 END
                        } else {
                            // 2015/02/19 Nishiyama Add エスケープ処理 START
                            if (strpos($option, "icon") > 0 || strpos($option, "no-escape-html") > 0) {
	                            $body_html .= '<td ' . $option . '  field="' . $k_col . '">' . $str_val . ' ' . $addcontent . ' </td>';
	                        } else if (isset($val_col['input'])) {
                                $inputData = $val_col['input'];
                                $inputHTML = '';
                                $inputValue = $str_val;
                                if (isset($options['values']) && isset($options['values'][$k_col])) {
                                    $inputValue = $options['values'][$k_col][$rowIndex];
                                }
                                
                                switch ($val_col['input']['type']) {
                                    case 'text':
                                        $inputHTML = '<input type="text" name="' . $inputData['name'] . '[]" value="' . $inputValue . '" ' . $inputData['attr'] .' />';
                                        break;
                                    default:
                                        break;
                                }
	                            $body_html .= '<td ' . $option . ' field="' . $k_col . '" >' . $inputHTML . $addcontent . ' </td>';
	                        } else {
	                            $body_html .= '<td ' . $option . ' field="' . $k_col . '" >' . htmlspecialchars($str_val) . $addcontent . ' </td>';
	                        }
                            // 2015/02/19 Nishiyama Add エスケープ処理 END
                        }
                    }
                } else {
                    //---not found the key in data.
                    $body_html .= '<td ' . $option . ' >  ' . $addcontent . '</td>';
                }
            }

            //---add tr in table
            if ($stt % 2 == 0) {
                $html .= '<tr class="even" id="list_data_' . $stt . '" >' . $body_html . '</tr>';
            } else {
                $html .= '<tr class="odd" id="list_data_' . $stt . '"  >' . $body_html . '</tr>';
            }
            $stt++;
        }

        //---data is null
        if (count($this->_data) == 0) {
            $html .= '<tr><td colspan="' . $colspan . '">データは登録されていません。</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';
        return $html;
    }

    /*
     * getCreateURL
     * create url
     * $action array
     * $params array
     * return string url
     */

    public function getCreateURL($action, $params) {
        $url = $action;
        foreach ($params as $key => $value) {
            if (!( $key == 'controller' || $key == 'action' || $key == 'module' )) {
                if (!is_array($value)) {
                    $value = trim($value);
                    if ($value != '') {
                        $url .= '/' . $key . '/' . $value;
                    }
                }
            }
        }
        return $url;
    }

    /*
     * formatMoney
     * add money curency & format number
     * $number num
     * return curency num
     */

    public static function formatMoney($number) {
        if (1 * $number > 0) {
            return '¥' . number_format($number, 0, '.', ',');
        } else {
            return '¥' . $number;
        }
    }

}