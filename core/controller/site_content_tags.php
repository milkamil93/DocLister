<?php
/**
 * site_content_tags controller with TagSaver plugin
 * @see http://modx.im/blog/addons/374.html
 *
 * @category controller
 * @license GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @author Agel_Nash <Agel_Nash@xaker.ru>
 * @date 26.08.2013
 * @version 1.0.23
 *
 * @TODO add parameter showFolder - include document container in result data whithout children document if you set depth parameter.
 */

class site_content_tagsDocLister extends DocLister
{
    private $tag = array();

    function __construct($modx, $cfg = array()){
        parent::__construct($modx,$cfg);
        if(!$this->_loadExtender('tv')){
            die('error');
        }
    }

    /**
     * @absctract
     * @todo link maybe include other GET parameter with use pagination. For example - filter
     */
    public function getUrl($id = 0)
    {
        $id = $id > 0 ? $id : $this->modx->documentIdentifier;
        $link = $this->checkExtender('request') ? $this->extender['request']->getLink() : "";
        $tag = $this->checkTag();
        if ($tag != false && is_array($tag) && $tag['mode'] == 'get') {
            $link .= "&tag=" . urlencode($tag['tag']);
        }
        $url = ($id == $this->modx->config['site_start']) ? $this->modx->config['site_url'] . ($link != '' ? "?{$link}" : "") : $this->modx->makeUrl($id, '', $link, 'full');
        return $url;
    }

    /**
    * @absctract
    */
    public function getDocs($tvlist = '')
    {
        $this->extender['tv']->getAllTV_Name();

        if ($this->checkExtender('paginate')) {
            $pages = $this->extender['paginate']->init($this);
        } else {
            $this->setConfig(array('start' => 0));
        }
        $type = $this->getCFGDef('idType', 'parents');
        $this->_docs = ($type == 'parents') ? $this->getChildrenList() : $this->getDocList();

        if ($tvlist == '') {
            $tvlist = $this->getCFGDef('tvList', '');
        }
        if ($tvlist != '' && $this->checkIDs()) {

            $tv = $this->extender['tv']->getTVList(array_keys($this->_docs),$tvlist);
            foreach ($tv as $docID => $TVitem) {
                $this->_docs[$docID] = array_merge($this->_docs[$docID], $TVitem);
            }
        }
        return $this->_docs;
    }

    /**
    * @absctract
    * @todo set correct active placeholder if you work with other table. Because $item['id'] can differ of $modx->documentIdentifier (for other controller)
    * @todo set author placeholder (author name). Get id from Createdby OR editedby AND get info from extender user
    * @todo set filter placeholder with string filtering for insert URL
    */
    public function _render($tpl = '')
    {
        $out = '';
        if ($tpl == '') {
            $tpl = $this->getCFGDef('tpl', '');
        }
        if ($tpl != '') {
            $date = $this->getCFGDef('dateSource', 'pub_date');

            $this->toPlaceholders(count($this->_docs), 1, "display"); // [+display+] - сколько показано на странице.

            $i = 1;
            $sysPlh = $this->renameKeyArr($this->_plh, $this->getCFGDef("sysKey", "dl"));
            $noneTPL = $this->getCFGDef("noneTPL", "");
            if (count($this->_docs) == 0 && $noneTPL != '') {
                $out = $this->parseChunk($noneTPL, $sysPlh);
            } else {
                if ($this->checkExtender('user')) {
                    $this->extender['user']->init($this, array('fields' => $this->getCFGDef("userFields", "")));
                }
                foreach ($this->_docs as $item) {
                    $subTpl = '';
                    if ($this->checkExtender('user')) {
                        $item = $this->extender['user']->setUserData($item); //[+user.id.createdby+], [+user.fullname.publishedby+], [+dl.user.publishedby+]....
                    }

                    if ($this->checkExtender('summary')) {
                        if (mb_strlen($item['introtext'], 'UTF-8') > 0) {
                            $item['summary'] = $item['introtext'];
                        } else {
                            $item['summary'] = $this->extender['summary']->init($this, array("content" => $item['content'], "summary" => $this->getCFGDef("summary", "")));
                        }
                    }

                    $item = array_merge($item, $sysPlh); //inside the chunks available all placeholders set via $modx->toPlaceholders with prefix id, and with prefix sysKey
                    $item['title'] = ($item['menutitle'] == '' ? $item['pagetitle'] : $item['menutitle']);
                    $item['iteration'] = $i; //[+iteration+] - Number element. Starting from zero

                    $item['url'] = ($item['type'] == 'reference') ? $item['content'] : $this->getUrl($item['id']);

                    $item['date'] = (isset($item[$date]) && $date != 'createdon' && $item[$date] != 0 && $item[$date] == (int)$item[$date]) ? $item[$date] : $item['createdon'];
                    $item['date'] = $item['date'] + $this->modx->config['server_offset_time'];
                    if ($this->getCFGDef('dateFormat', '%d.%b.%y %H:%M') != '') {
                        $item['date'] = strftime($this->getCFGDef('dateFormat', '%d.%b.%y %H:%M'), $item['date']);
                    }

                    $class = array();
                    $class[] = ($i % 2 == 0) ? 'odd' : 'even';
                    if ($i == 0) {
                        $subTpl = $this->getCFGDef('tplFirst', $tpl);
                        $class[] = 'first';
                    }
                    if ($i == count($this->_docs)) {
                        $subTpl = $this->getCFGDef('tplLast', $tpl);
                        $class[] = 'last';
                    }
                    if ($this->modx->documentIdentifier == $item['id']) {
                        $subTpl = $this->getCFGDef('tplCurrent', $tpl);
                        $item[$this->getCFGDef("sysKey", "dl") . '.active'] = 1; //[+active+] - 1 if $modx->documentIdentifer equal ID this element
                        $class[] = 'current';
                    } else {
                        $item['active'] = 0;
                    }
                    $class = implode(" ", $class);
                    $item[$this->getCFGDef("sysKey", "dl") . '.class'] = $class;
                    if($subTpl==''){
                        $subTpl = $tpl;
                    }
                    if($this->checkExtender('prepare')){
                        $item = $this->extender['prepare']->init($this, $item);
                    }
                    $tmp = $this->parseChunk($subTpl, $item);
                    if ($this->getCFGDef('contentPlaceholder', 0) !== 0) {
                        $this->toPlaceholders($tmp, 1, "item[" . $i . "]"); // [+item[x]+] – individual placeholder for each iteration documents on this page
                    }
                    $out .= $tmp;
                    $i++;
                }
            }
            if (($this->getCFGDef("noneWrapOuter", "1") && count($this->_docs) == 0) || count($this->_docs) > 0) {
                $ownerTPL = $this->getCFGDef("ownerTPL", "");
                // echo $this->modx->getChunk($ownerTPL);
                if ($ownerTPL != '') {
                    $out = $this->parseChunk($ownerTPL, array($this->getCFGDef("sysKey", "dl") . ".wrap" => $out));
                }
            }
        } else {
            $out = 'none TPL';
        }

        return $this->toPlaceholders($out);
    }

    public function getJSON($data, $fields, $array = array())
    {
        $out = array();
        $fields = is_array($fields) ? $fields : explode(",", $fields);
        $date = $this->getCFGDef('dateSource', 'pub_date');

        foreach ($data as $num => $item) {
            switch (true) {
                case ((array('1') == $fields || in_array('summary', $fields)) && $this->checkExtender('summary')):
                {
                    $out[$num]['summary'] = (mb_strlen($this->_docs[$num]['introtext'], 'UTF-8') > 0) ? $this->_docs[$num]['introtext'] : $this->extender['summary']->init($this, array("content" => $this->_docs[$num]['content'], "summary" => $this->getCFGDef("summary", "")));
                    //without break
                }
                case (array('1') == $fields || in_array('date', $fields)):
                {
                    $tmp = (isset($this->_docs[$num][$date]) && $date != 'createdon' && $this->_docs[$num][$date] != 0 && $this->_docs[$num][$date] == (int)$this->_docs[$num][$date]) ? $this->_docs[$num][$date] : $this->_docs[$num]['createdon'];
                    $out[$num]['date'] = strftime($this->getCFGDef('dateFormat', '%d.%b.%y %H:%M'), $tmp + $this->modx->config['server_offset_time']);
                    //without break
                }
            }
        }

        return parent::getJSON($data, $fields, $out);
    }

    /**
     * document
     */

    // @abstract
    public function getChildrenCount()
    {
        $where = $this->getCFGDef('addWhereList', '');
        if ($where != '') {
            $where .= " AND ";
        }
        $wheres = $this->whereTag($where);
        $tbl_site_content = $this->getTable('site_content','c');
        $sanitarInIDs = $this->sanitarIn($this->IDs);
        $getCFGDef = $this->getCFGDef('showParent', '0') ? '' : "AND c.id NOT IN({$sanitarInIDs})";
        $fields = 'count(c.`id`) as `count`';
        $from = "{$tbl_site_content} {$wheres['join']}";
        $where = "{$wheres['where']} c.parent IN ({$sanitarInIDs}) AND c.deleted=0 AND c.published=1 {$getCFGDef}";
        $rs = $this->modx->db->select($fields, $from, $where);
        return $this->modx->db->getValue($rs);
    }

    protected function getDocList()
    {
        /**
        * @TODO: 3) Формирование ленты в случайном порядке (если отключена пагинация и есть соответствующий запрос)
        * @TODO: 5) Добавить фильтрацию по основным параметрам документа
        */
        $where = $this->getCFGDef('addWhereList', '');
        if ($where != '') {
            $where .= " AND ";
        }

        $tbl_site_content = $this->getTable('site_content','c');
        $sanitarInIDs = $this->sanitarIn($this->IDs);
        $where = "WHERE {$where} c.id IN ({$sanitarInIDs}) AND c.deleted=0 AND c.published=1";
        $limit = $this->LimitSQL($this->getCFGDef('queryLimit', 0));
        $select = "c.*";
        $sort = $this->SortOrderSQL("if(c.pub_date=0,c.createdon,c.pub_date)");
        if (preg_match("/^ORDER BY (.*) /", $sort, $match)) {
            $TVnames = $this->extender['tv']->getTVnames();
            if (isset($TVnames[$match[1]])) {
                $tbl_site_content .= " LEFT JOIN " . $this->getTable("site_tmplvar_contentvalues") . " as tv
                    on tv.contentid=c.id AND tv.tmplvarid=" . $TVnames[$match[1]];
                $sort = str_replace("ORDER BY " . $match[1], "ORDER BY tv.value", $sort);
            }
        }

        $rs = $this->modx->db->query("SELECT {$select}  FROM {$tbl_site_content} {$where} GROUP BY c.id {$sort} {$limit}");

        $rows = $this->modx->db->makeArray($rs);
        $out = array();
        foreach ($rows as $item) {
            $out[$item['id']] = $item;
        }
        return $out;
    }

    public function getChildernFolder($id)
    {
        /**
        * @TODO: 3) Формирование ленты в случайном порядке (если отключена пагинация и есть соответствующий запрос)
        * @TODO: 5) Добавить фильтрацию по основным параметрам документа
        */
        $where = $this->getCFGDef('addWhereFolder', '');
        if ($where != '') {
            $where .= " AND ";
        }

        $tbl_site_content = $this->getTable('site_content','c');
        $sanitarInIDs = $this->sanitarIn($id);
        $where = "{$where} c.parent IN ({$sanitarInIDs}) AND c.deleted=0 AND c.published=1 AND c.isfolder=1";
        $rs = $this->modx->db->select('id', $tbl_site_content, $where);

        $rows = $this->modx->db->makeArray($rs);
        $out = array();
        foreach ($rows as $item) {
            $out[] = $item['id'];
        }
        return $out;
    }

    private function getTag()
    {
        $tags = $this->getCFGDef('tagsData', '');
        $this->tag = array();
        if ($tags != '') {
            $tmp = explode(":", $tags, 2);
            if (count($tmp) == 2) {
                switch ($tmp[0]) {
                    case 'get':
                    {
                        $tag = (isset($_GET[$tmp[1]]) && !is_array($_GET[$tmp[1]])) ? $_GET[$tmp[1]] : '';
                        break;
                    }
                    case 'static':
                    default:
                        {
                        $tag = $tmp[1];
                        break;
                        }
                }
                $this->tag = array("mode" => $tmp[0], "tag" => $tag);
                $this->toPlaceholders($this->sanitarData($tag), 1, "tag");
            }
        }
        return $this->checkTag();
    }

    private function checkTag($reconst = false)
    {
        $data = (is_array($this->tag) && count($this->tag) == 2 && isset($this->tag['tag']) && $this->tag['tag'] != '') ? $this->tag : false;
        if ($data === false && $reconst === true) {
            $data = $this->getTag();
        }
        return $data;
    }

    private function whereTag($where)
    {
        $join = '';
        $tag = $this->checkTag(true);
        if ($tag !== false) {
            $join = "RIGHT JOIN " . $this->getTable('site_content_tags','ct') . " on ct.doc_id=c.id
					RIGHT JOIN " . $this->getTable('tags','t') . " on t.id=ct.tag_id";
            $where .= "t.`name`='" . $this->modx->db->escape($tag['tag']) . "'" .
                (($this->getCFGDef('tagsData', '') > 0) ? "AND ct.tv_id=" . (int)$this->getCFGDef('tagsData', '') : "") . " AND ";
        }
        $out = array("where" => $where, "join" => $join);
        return $out;
    }

    /**
	* @TODO: 3) Формирование ленты в случайном порядке (если отключена пагинация и есть соответствующий запрос)
	* @TODO: 5) Добавить фильтрацию по основным параметрам документа
	*/
    protected function getChildrenList()
    {

        $where = $this->getCFGDef('addWhereList', '');
        if ($where != '') {
            $where .= " AND ";
        }
        $where = $this->whereTag($where);

        $sql = $this->modx->db->query("
			SELECT c.* FROM " . $this->getTable('site_content','c') . $where['join'] . "
			WHERE " . $where['where'] . "
				c.parent IN (" . $this->sanitarIn($this->IDs) . ")
				AND c.deleted=0 
				AND c.published=1 " .
                (($this->getCFGDef('showParent', '0')) ? "" : "AND c.id NOT IN(" . $this->sanitarIn($this->IDs) . ") ") .
                $this->SortOrderSQL('if(c.pub_date=0,c.createdon,c.pub_date)') . " " .
                $this->LimitSQL($this->getCFGDef('queryLimit', 0))
        );
        $rows = $this->modx->db->makeArray($sql);
        $out = array();
        foreach ($rows as $item) {
            $out[$item['id']] = $item;
        }
        return $out;
    }
}

?>