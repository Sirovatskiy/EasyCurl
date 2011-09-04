<?
/*!
*	EasyCurl - let you to use curl in a simple way
* 	Copyright Pavel Sirovatskiy
* 	Licensed under GPL2
*/

class EasyCurl
{
	// TODO: easy cookie?

	public $wait_time = 100000; //milliseconds
	private $proxy_list = null;
	private $data = array();
	private $mh;
	private $ch = array();
	private $user_agents = array
	(
		'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.8.0.1) Gecko/20060111 Firefox/1.5.0.1',
		'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.11) Gecko/2009060215 Firefox/3.0.11',
		'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; ru; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12',
		'Opera/9.80 (Windows NT 5.1; U; ru) Presto/2.6.30 Version/10.63',
		'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/534.7 (KHTML, like Gecko) Chrome/7.0.517.44 Safari/534.7'
	);
	private $static_options = array
	(
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_FOLLOWLOCATION => 1,
		CURLOPT_HEADER => 0,
		CURLOPT_TIMEOUT => 20,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_ENCODING => '',
		CURLOPT_USERAGENT => 'r'
	);
	private $static_headers = array
	(
		'Accept' => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
		'Accept-Language' => 'ru,en;q=0.5',
		'Accept-Charset' => 'windows-1251,utf-8;q=0.7,*;q=0.7',
		'Keep-Alive' => '300',
		'Connection' => 'keep-alive',
		'Pragma' => 'no-cache',
		'Cache-Control' => 'no-cache'
	);

	// get random ip from private networks
	public function get_random_private_ip()
	{
		switch (rand(0, 2))
		{
			case 0:
				return '192.168.'.rand(1, 254).'.'.rand(1, 254);
			case 1:
				return '172.16.'.rand(1, 254).'.'.rand(1, 254);
			case 2:
				return '10.'.rand(1, 254).'.'.rand(1, 254).'.'.rand(1, 254);
		}
	}

	// get next proxy from list
	public function next_proxy()
	{
		if (is_array($this->proxy_list) && count($this->proxy_list) > 0)
		{
			$nxt = current($this->proxy_list);
			if (next($this->proxy_list) === false)
				reset($this->proxy_list);
			return trim($nxt);
		}
		else
			return false;
	}

	// constructor
	public function __construct($static_options = array(), $static_headers = array())
	{
		$this->set_static_options($static_options);
		$this->set_static_headers($static_headers);
	}

	// set opts
	public function set_static_options($static_options)
	{
		$this->static_options = $static_options + $this->static_options;
	}

	//set static headers
	public function set_static_headers($static_headers)
	{
		$this->static_headers = $static_headers + $this->static_headers;
	}

	// set user agents
	public function set_user_agents($user_agents, $add = true)
	{
		$this->user_agents = $add? array_merge($this->user_agents, $user_agents) : $user_agents;
	}

	// set proxy list link
	public function set_proxy_list_link(&$proxy_list)
	{
		$this->proxy_list = &$proxy_list;
	}
	// clear proxy list link
	public function clear_proxy_list_link()
	{
		$this->proxy_list = null;
	}

	// prepare handlers
	public function prepare($urls, $options = array(), $headers = array())
	{
		$this->ch = array();
		$this->data = array();

		foreach ($urls as $k => $url)
		{
			$this->ch[$k] = curl_init();

			// headers
			$headers[$k] = isset($headers[$k])? $headers[$k] + $this->static_headers : $this->static_headers;
			$hdrs = array();
			foreach ($headers[$k] as $hdr => $hdr_value)
			{
				if ($hdr == 'X-Forwarded-For' && $hdr_value == 'r')
					$hdr_value = $this->get_random_private_ip();
				$hdrs[] = $hdr.': '.$hdr_value;
			}

			// options
			$options[$k] = isset($options[$k])? $options[$k] + $this->static_options : $this->static_options;
			$options[$k][CURLOPT_URL] = $url;
			$options[$k][CURLOPT_HTTPHEADER] = $hdrs;

			if (!isset($options[$k][CURLOPT_PROXY]) && is_array($this->proxy_list) && count($this->proxy_list) > 0)
			{
				list ($proxy, $proxy_pwd) = explode(',', $this->next_proxy());

				$options[$k][CURLOPT_PROXY] = $proxy;
				if ($proxy_pwd != '')
					$options[$k][CURLOPT_PROXYUSERPWD] = $proxy_pwd;
			}
			if (isset($options[$k][CURLOPT_USERAGENT]) && $options[$k][CURLOPT_USERAGENT] == 'r')
				$options[$k][CURLOPT_USERAGENT] = $this->user_agents[rand(0, count($this->user_agents)-1)];
			if (isset($options[$k][CURLOPT_PROXY]) && $options[$k][CURLOPT_PROXY] != '')
				$this->data[$k]['proxy'] = $options[$k][CURLOPT_PROXY];

			curl_setopt_array($this->ch[$k], $options[$k]);
		}
	}

	// execute handlers
	public function execute(&$data)
	{
		$this->mh = curl_multi_init();
		foreach (array_keys($this->ch) as $k)
			curl_multi_add_handle($this->mh, $this->ch[$k]);

		do
		{
			$mrc = curl_multi_exec($this->mh, $run_count);
			usleep($this->wait_time);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM || $run_count > 0);

		foreach (array_keys($this->ch) as $k)
		{
			$inf = curl_multi_info_read($this->mh);

			$this->data[$k]['cont'] = curl_multi_getcontent($this->ch[$k]);
			$this->data[$k]['info'] = curl_getinfo($this->ch[$k]);
			$this->data[$k]['error'] = curl_error($this->ch[$k]);
			$this->data[$k]['errno'] = $inf['result'];
			//$this->data[$k]['errno'] = curl_errno($this->ch[$k]); //for better times
			curl_multi_remove_handle($this->mh, $this->ch[$k]);
			curl_close($this->ch[$k]);
		}
		curl_multi_close($this->mh);

		$data = $this->data;
	}

	//fast
	public function fast($url, &$data, $options = array(), $headers = array())
	{
		if (!is_string($url))
			return false;

		$this->prepare(array($url), array($options), array($headers));

		$this->data[0]['cont'] = curl_exec($this->ch[0]);
		$this->data[0]['info'] = curl_getinfo($this->ch[0]);
		$this->data[0]['error'] = curl_error($this->ch[0]);
		$this->data[0]['errno'] = curl_errno($this->ch[0]);
		curl_close($this->ch[0]);

		$data = $this->data[0];
	}

}
?>