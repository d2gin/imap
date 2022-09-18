<?php

namespace icy8\imap;
class Inbox
{
    const connect_fail = 5001;
    public    $server        = '';
    public    $port          = '143';
    public    $user          = '';
    public    $password      = '';
    public    $link          = '';
    protected $decodeClosure = null;
    protected $mbox          = null;
    protected $headers       = [];
    protected $options       = [
        'limit'      => 0,
        'is_reverse' => true,
    ];

    public function __construct()
    {
        $this->decodeClosure = function ($content) {
            return base64_decode($content, true) ?: $content;
        };
    }

    public function config($config = [])
    {
        $field = ['server', 'port', 'user', 'password', 'link'];
        foreach ($field as $v) {
            if (property_exists($this, $v) && isset($config[$v])) {
                $this->$v = $config[$v];
            }
        }
        return $this;
    }

    /**
     * 开始连接
     * @return $this
     * @throws \Exception
     */
    public function open()
    {
        $this->link = "{{$this->server}:{$this->port}}INBOX";
        $this->mbox = @imap_open($this->link, $this->user, $this->password);
        if (!$this->mbox) {
            throw new \Exception("connect fail", self::connect_fail);
        }
        return $this;
    }

    /**
     * @param int $size 条数
     * @param null $offset 数据开始位置
     * @return $this
     */
    public function limit($size = 1, $offset = null)
    {
        if (is_numeric($size) && is_numeric($offset)) {
            $this->options['limit'] = [$size, $offset];
        } else $this->options['limit'] = $size;
        return $this;
    }

    public function find($id)
    {
        return $this->fetch($id);
    }

    /**
     * 获取一条数据
     * @param string $id
     * @return array|mixed|null
     */
    public function fetch($id = '')
    {
        if ($id) {
            return $this->fetchBody($id);
        }
        $list = $this->limit(1)->fetchAll();
        return $list[0] ?? null;
    }

    /**
     * 获取所有数据
     * @return array
     */
    public function fetchAll()
    {
        $msg   = [];
        $start = 1;
        $end   = 1;
        $total = $this->getMessageNum();
        if ($total == 0) {
            // 没有邮件
            $this->reset();
            return [];
        }
        if (!$this->options['limit']) {
            $this->options['limit'] = [$total, $this->options['is_reverse'] ? $total : $start];
        } else if (is_numeric($this->options['limit'])) {
            $this->options['limit'] = [$this->options['limit'], $this->options['is_reverse'] ? $total : $start];
        }
        if (is_array($this->options['limit'])) {
            $offset = $this->options['limit'][1];
            $size   = $this->options['limit'][0];
            $start  = $offset;
            $end    = $this->options['is_reverse'] ? ($start - $size + 1) : ($size + $start - 1);
        } else {
            $end = $this->options['limit'];
        }
        $range = range($start, $end);
        foreach ($range as $id) {
            $msg[] = $this->fetchBody($id);
        }
        $this->reset();
        return $msg;
    }

    public function fetchBody($id)
    {
        $mailBody           = imap_fetchbody($this->mbox, $id, 1); //获取信件正文
        $headers            = imap_fetchheader($this->mbox, $id);  //获取信件标头
        $headers            = $this->formatHeaders($headers);
        $this->headers[$id] = $headers;
        $receive            = $headers['received'] ?? '';
        $array              = explode(';', $receive);
        $date               = end($array) ?: '';
        $from               = $this->formatFrom($headers['from'] ?? '');
        $title              = $headers['subject'] ?? '';
        if ($date) {
            $date = date('Y-m-d H:i:s', strtotime(trim($date)));
        }
        return [
            'id'      => $id,
            'title'   => $title,
            'from'    => $from,
            'content' => $this->decode($mailBody),
            'date'    => $date,
        ];
    }

    /**
     * 数据倒序
     * @return $this
     */
    public function desc()
    {
        $this->options['is_reverse'] = true;
        return $this;
    }

    /**
     * 数据顺序
     * @return $this
     */
    public function asc()
    {
        $this->options['is_reverse'] = false;
        return $this;
    }

    /**
     * 获取邮件数
     * @return int
     */
    public function getMessageNum()
    {
        return imap_num_msg($this->mbox);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    protected function formatFrom($from)
    {
        if (preg_match('!\<(.+)\>!is', $from, $m)) {
            $from = $m[1];
        }
        return $from;
    }

    protected function formatHeaders($lines)
    {
        if (!is_array($lines)) {
            $lines = explode("\r\n", $lines);
        }
        $lines   = array_filter($lines);
        $headers = [];
        foreach ($lines as $header) {
            list($name, $value) = explode(":", $header, 2);
            $value       = trim($value);
            $valueDecode = imap_mime_header_decode($value);
            $value       = '';
            foreach ($valueDecode as $item) {
                $value .= $item->text;
            }
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }

    protected function decode($content)
    {
        if (!$this->decodeClosure) {
            return $content;
        }
        return call_user_func_array($this->decodeClosure, [$content]);
    }

    public function reset()
    {
        $this->options = [
            'limit'      => 0,
            'is_reverse' => true,
        ];
    }

    public function __destruct()
    {
        if ($this->mbox) imap_close($this->mbox);
    }
}
