<?php

namespace Sifoni\Adapter;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;

class SifoniSessionStorage extends NativeSessionStorage
{
    protected $options = [];

    public function __construct(array $options = array(), $handler = null, MetadataBag $metaBag = null)
    {
        session_cache_limiter('');
        session_register_shutdown();
        $this->setMetadataBag($metaBag);
        $this->setOptions($options);
        $this->setSaveHandler($handler);
    }

    public function start()
    {
        if ($this->started) {
            return true;
        }
        if (\PHP_SESSION_ACTIVE === session_status()) {
            throw new \RuntimeException('Failed to start the session: already started by PHP.');
        }
        if (ini_get('session.use_cookies') && headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf('Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line));
        }
        if (!session_start($this->options)) {
            throw new \RuntimeException('Failed to start the session');
        }
        $this->loadSession();

        return true;
    }

    public function regenerate($destroy = false, $lifetime = null)
    {
        if (\PHP_SESSION_ACTIVE !== session_status()) {
            return false;
        }
        if (null !== $lifetime && strpos(ini_get('disable_functions'), 'ini_set') === false) {
            ini_set('session.cookie_lifetime', $lifetime);
        }
        if ($destroy) {
            $this->metadataBag->stampNew();
        }
        $isRegenerated = session_regenerate_id($destroy);
        $this->loadSession();

        return $isRegenerated;
    }

    public function setOptions(array $options)
    {
        $validOptions = array(
            'cache_limiter', 'cookie_domain', 'cookie_httponly',
            'cookie_lifetime', 'cookie_path', 'cookie_secure',
            'entropy_file', 'entropy_length', 'gc_divisor',
            'gc_maxlifetime', 'gc_probability', 'hash_bits_per_character',
            'hash_function', 'name', 'referer_check',
            'serialize_handler', 'use_cookies',
            'use_only_cookies', 'use_trans_sid', 'upload_progress.enabled',
            'upload_progress.cleanup', 'upload_progress.prefix', 'upload_progress.name',
            'upload_progress.freq', 'upload_progress.min-freq', 'url_rewriter.tags',
        );
        foreach ($options as $key => $value) {
            if (in_array($key, $validOptions)) {
                $this->options[$key] = $value;
            }
        }
    }
}
