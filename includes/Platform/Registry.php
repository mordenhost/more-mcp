<?php
namespace More_MCP\Platform;

if (!defined('ABSPATH')) {
    exit;
}

class Registry {

    public static function get_platforms() {
        return [
            'claude' => [
                'id' => 'claude',
                'label' => 'Claude (Anthropic)',
                'icon' => 'anthropic',
                'color' => '#6B4C9A',
                'description' => 'Anthropic\'s Claude AI - Advanced reasoning and analysis',
                'docs_url' => 'https://console.anthropic.com/',
                'api_key_url' => 'https://console.anthropic.com/settings/keys',
                'endpoint' => 'https://api.anthropic.com',
                'auth_type' => 'header',
                'auth_header' => 'x-api-key',
                'extra_headers' => [
                    'anthropic-version' => '2023-06-01',
                ],
                'fields' => [
                    'api_key' => [
                        'type' => 'password',
                        'label' => 'API Key',
                        'required' => true,
                        'placeholder' => 'sk-ant-...',
                        'help' => 'Get your API key from the Anthropic Console',
                    ],
                    'model' => [
                        'type' => 'select',
                        'label' => 'Model',
                        'required' => false,
                        'default' => 'claude-sonnet-4-6',
                        'options' => [
                            'claude-opus-4-7' => 'Claude Opus 4.7 (Most Capable)',
                            'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (Latest, Recommended)',
                            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Fast & Cheap)',
                        ],
                    ],
                ],
                'test_endpoint' => '/v1/messages',
                'test_method' => 'POST',
                'test_body' => [
                    'model' => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 10,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hi']
                    ]
                ],
            ],

            'openai' => [
                'id' => 'openai',
                'label' => 'OpenAI / ChatGPT',
                'icon' => 'openai',
                'color' => '#10A37F',
                'description' => 'OpenAI\'s GPT models - ChatGPT and GPT-4',
                'docs_url' => 'https://platform.openai.com/docs',
                'api_key_url' => 'https://platform.openai.com/api-keys',
                'endpoint' => 'https://api.openai.com',
                'auth_type' => 'bearer',
                'auth_header' => 'Authorization',
                'fields' => [
                    'api_key' => [
                        'type' => 'password',
                        'label' => 'API Key',
                        'required' => true,
                        'placeholder' => 'sk-...',
                        'help' => 'Get your API key from OpenAI Platform',
                    ],
                    'model' => [
                        'type' => 'select',
                        'label' => 'Model',
                        'required' => false,
                        'default' => 'gpt-5',
                        'options' => [
                            'gpt-5.5' => 'GPT-5.5 (Most Capable)',
                            'gpt-5' => 'GPT-5 (Latest, Recommended)',
                            'gpt-5-mini' => 'GPT-5 Mini (Fast & Affordable)',
                            'gpt-5-nano' => 'GPT-5 Nano (Fastest & Cheapest)',
                            'o3' => 'o3 (Reasoning)',
                            'gpt-4.1' => 'GPT-4.1 (Legacy)',
                            'gpt-4o' => 'GPT-4o (Legacy)',
                        ],
                    ],
                    'organization_id' => [
                        'type' => 'text',
                        'label' => 'Organization ID',
                        'required' => false,
                        'placeholder' => 'org-... (optional)',
                        'help' => 'Only needed if you belong to multiple organizations',
                    ],
                ],
                'test_endpoint' => '/v1/models',
                'test_method' => 'GET',
            ],

            'google' => [
                'id' => 'google',
                'label' => 'Google Gemini',
                'icon' => 'google',
                'color' => '#4285F4',
                'description' => 'Google\'s Gemini AI - Multimodal capabilities',
                'docs_url' => 'https://ai.google.dev/docs',
                'api_key_url' => 'https://aistudio.google.com/app/apikey',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
                'auth_type' => 'query',
                'auth_param' => 'key',
                'fields' => [
                    'api_key' => [
                        'type' => 'password',
                        'label' => 'API Key',
                        'required' => true,
                        'placeholder' => 'AI...',
                        'help' => 'Get your API key from Google AI Studio',
                    ],
                    'model' => [
                        'type' => 'select',
                        'label' => 'Model',
                        'required' => false,
                        'default' => 'gemini-3.5-flash',
                        'options' => [
                            'gemini-3.5-flash' => 'Gemini 3.5 Flash (Latest, Recommended)',
                            'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite (Fast & Cheap)',
                        ],
                    ],
                ],
                'test_endpoint' => '/models',
                'test_method' => 'GET',
            ],

            'ollama' => [
                'id' => 'ollama',
                'label' => 'Ollama (Local)',
                'icon' => 'ollama',
                'color' => '#000000',
                'description' => 'Run AI models locally on your machine',
                'docs_url' => 'https://ollama.ai/',
                'api_key_url' => null,
                'endpoint' => 'http://localhost:11434',
                'auth_type' => 'none',
                'fields' => [
                    'url' => [
                        'type' => 'url',
                        'label' => 'Ollama URL',
                        'required' => true,
                        'default' => 'http://localhost:11434',
                        'placeholder' => 'http://localhost:11434',
                        'help' => 'URL where Ollama is running',
                    ],
                    'model' => [
                        'type' => 'text',
                        'label' => 'Model Name',
                        'required' => true,
                        'default' => 'llama3.2',
                        'placeholder' => 'llama3.2, mistral, codellama, etc.',
                        'help' => 'Model must be pulled first: ollama pull model-name',
                    ],
                ],
                'test_endpoint' => '/api/tags',
                'test_method' => 'GET',
            ],

            'lmstudio' => [
                'id' => 'lmstudio',
                'label' => 'LM Studio (Local)',
                'icon' => 'lmstudio',
                'color' => '#6366F1',
                'description' => 'Run local models with LM Studio',
                'docs_url' => 'https://lmstudio.ai/',
                'api_key_url' => null,
                'endpoint' => 'http://localhost:1234/v1',
                'auth_type' => 'none',
                'fields' => [
                    'url' => [
                        'type' => 'url',
                        'label' => 'LM Studio URL',
                        'required' => true,
                        'default' => 'http://localhost:1234/v1',
                        'placeholder' => 'http://localhost:1234/v1',
                        'help' => 'Start the local server in LM Studio first',
                    ],
                ],
                'test_endpoint' => '/models',
                'test_method' => 'GET',
            ],

            'groq' => [
                'id' => 'groq',
                'label' => 'Groq',
                'icon' => 'groq',
                'color' => '#F55036',
                'description' => 'Ultra-fast inference with Groq LPU',
                'docs_url' => 'https://console.groq.com/docs',
                'api_key_url' => 'https://console.groq.com/keys',
                'endpoint' => 'https://api.groq.com/openai/v1',
                'auth_type' => 'bearer',
                'auth_header' => 'Authorization',
                'fields' => [
                    'api_key' => [
                        'type' => 'password',
                        'label' => 'API Key',
                        'required' => true,
                        'placeholder' => 'gsk_...',
                        'help' => 'Get your API key from Groq Console',
                    ],
                    'model' => [
                        'type' => 'select',
                        'label' => 'Model',
                        'required' => false,
                        'default' => 'llama-3.3-70b-versatile',
                        'options' => [
                            'llama-3.3-70b-versatile' => 'Llama 3.3 70B (Recommended)',
                            'llama-3.1-8b-instant' => 'Llama 3.1 8B (Fast & Cheap)',
                            'openai/gpt-oss-120b' => 'GPT-OSS 120B (Open Weight)',
                            'openai/gpt-oss-20b' => 'GPT-OSS 20B (Open Weight, Smaller)',
                        ],
                    ],
                ],
                'test_endpoint' => '/models',
                'test_method' => 'GET',
            ],

            'azure' => [
                'id' => 'azure',
                'label' => 'Azure OpenAI',
                'icon' => 'azure',
                'color' => '#0078D4',
                'description' => 'OpenAI models on Microsoft Azure',
                'docs_url' => 'https://learn.microsoft.com/en-us/azure/ai-services/openai/',
                'api_key_url' => 'https://portal.azure.com/',
                'endpoint' => '',
                'auth_type' => 'header',
                'auth_header' => 'api-key',
                'fields' => [
                    'url' => [
                        'type' => 'url',
                        'label' => 'Endpoint URL',
                        'required' => true,
                        'placeholder' => 'https://your-resource.openai.azure.com',
                        'help' => 'Your Azure OpenAI resource endpoint',
                    ],
                    'api_key' => [
                        'type' => 'password',
                        'label' => 'API Key',
                        'required' => true,
                        'placeholder' => 'Your Azure API key',
                        'help' => 'Found in Azure Portal > Keys and Endpoint',
                    ],
                    'deployment' => [
                        'type' => 'text',
                        'label' => 'Deployment Name',
                        'required' => true,
                        'placeholder' => 'gpt-4-deployment',
                        'help' => 'The name of your model deployment',
                    ],
                    'api_version' => [
                        'type' => 'text',
                        'label' => 'API Version',
                        'required' => false,
                        'default' => '2024-02-15-preview',
                        'placeholder' => '2024-02-15-preview',
                    ],
                ],
                'test_endpoint' => '/openai/deployments?api-version=2024-02-15-preview',
                'test_method' => 'GET',
            ],

            'bedrock' => [
                'id' => 'bedrock',
                'label' => 'AWS Bedrock',
                'icon' => 'aws',
                'color' => '#FF9900',
                'description' => 'AWS Bedrock - Claude, Llama, and more',
                'docs_url' => 'https://docs.aws.amazon.com/bedrock/',
                'api_key_url' => 'https://console.aws.amazon.com/iam/',
                'endpoint' => '',
                'auth_type' => 'aws',
                'fields' => [
                    'region' => [
                        'type' => 'select',
                        'label' => 'AWS Region',
                        'required' => true,
                        'default' => 'us-east-1',
                        'options' => [
                            'us-east-1' => 'US East (N. Virginia)',
                            'us-west-2' => 'US West (Oregon)',
                            'eu-west-1' => 'Europe (Ireland)',
                            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                        ],
                    ],
                    'access_key' => [
                        'type' => 'password',
                        'label' => 'Access Key ID',
                        'required' => true,
                        'placeholder' => 'AKIA...',
                    ],
                    'secret_key' => [
                        'type' => 'password',
                        'label' => 'Secret Access Key',
                        'required' => true,
                        'placeholder' => 'Your secret key',
                    ],
                    'model' => [
                        'type' => 'select',
                        'label' => 'Model',
                        'required' => false,
                        'default' => 'anthropic.claude-sonnet-4-6-v1:0',
                        'options' => [
                            'anthropic.claude-opus-4-7-v1:0' => 'Claude Opus 4.7 (Most Capable)',
                            'anthropic.claude-sonnet-4-6-v1:0' => 'Claude Sonnet 4.6 (Recommended)',
                            'anthropic.claude-haiku-4-5-20251001-v1:0' => 'Claude Haiku 4.5 (Fast & Cheap)',
                            'amazon.nova-2-lite-v1:0' => 'Amazon Nova 2 Lite (Reasoning)',
                            'amazon.nova-pro-v1:0' => 'Amazon Nova Pro (Multimodal)',
                            'meta.llama3-3-70b-instruct-v1:0' => 'Llama 3.3 70B',
                        ],
                    ],
                ],
            ],

            'custom' => [
                'id' => 'custom',
                'label' => 'Custom MCP Server',
                'icon' => 'custom',
                'color' => '#6B7280',
                'description' => 'Configure any MCP-compatible server',
                'docs_url' => null,
                'api_key_url' => null,
                'endpoint' => '',
                'auth_type' => 'configurable',
                'fields' => [
                    'name' => [
                        'type' => 'text',
                        'label' => 'Server Name',
                        'required' => true,
                        'placeholder' => 'My Custom Server',
                        'help' => 'A friendly name for this server',
                    ],
                    'url' => [
                        'type' => 'url',
                        'label' => 'Server URL',
                        'required' => true,
                        'placeholder' => 'https://your-server.com/mcp',
                        'help' => 'The base URL of your MCP server',
                    ],
                    'auth_type' => [
                        'type' => 'select',
                        'label' => 'Authentication Type',
                        'required' => false,
                        'default' => 'bearer',
                        'options' => [
                            'none' => 'No Authentication',
                            'bearer' => 'Bearer Token',
                            'header' => 'Custom Header',
                            'basic' => 'Basic Auth',
                        ],
                    ],
                    'api_key' => [
                        'type' => 'password',
                        'label' => 'API Key / Token',
                        'required' => false,
                        'placeholder' => 'Your API key or token',
                    ],
                    'custom_header' => [
                        'type' => 'text',
                        'label' => 'Custom Header Name',
                        'required' => false,
                        'placeholder' => 'X-API-Key',
                        'help' => 'Only used with Custom Header auth type',
                    ],
                ],
            ],
        ];
    }

    public static function get_platform($platform_id) {
        $platforms = self::get_platforms();
        return $platforms[$platform_id] ?? null;
    }

    public static function get_platform_options() {
        $platforms = self::get_platforms();
        $options = [];

        foreach ($platforms as $id => $platform) {
            $options[$id] = $platform['label'];
        }

        return $options;
    }

    public static function get_platform_groups() {
        return [
            'cloud' => [
                'label' => 'Cloud AI Providers',
                'platforms' => ['claude', 'openai', 'google', 'groq'],
            ],
            'local' => [
                'label' => 'Local / Self-Hosted',
                'platforms' => ['ollama', 'lmstudio'],
            ],
            'enterprise' => [
                'label' => 'Enterprise / Cloud',
                'platforms' => ['azure', 'bedrock'],
            ],
            'other' => [
                'label' => 'Other',
                'platforms' => ['custom'],
            ],
        ];
    }

    public static function validate_config($platform_id, $config) {
        $platform = self::get_platform($platform_id);

        if (!$platform) {
            return new \WP_Error('invalid_platform', 'Invalid platform selected');
        }

        $errors = [];

        foreach ($platform['fields'] as $field_id => $field) {
            if (!empty($field['required']) && empty($config[$field_id])) {
                $errors[] = sprintf('%s is required', $field['label']);
            }
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', implode(', ', $errors));
        }

        return true;
    }

    public static function get_auth_headers($platform_id, $config) {
        $platform = self::get_platform($platform_id);

        if (!$platform) {
            return [];
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        switch ($platform['auth_type']) {
            case 'bearer':
                if (!empty($config['api_key'])) {
                    $headers['Authorization'] = 'Bearer ' . $config['api_key'];
                }
                break;

            case 'header':
                if (!empty($config['api_key']) && !empty($platform['auth_header'])) {
                    $headers[$platform['auth_header']] = $config['api_key'];
                }
                break;

            case 'configurable':
                if (!empty($config['api_key'])) {
                    $auth_type = $config['auth_type'] ?? 'bearer';
                    switch ($auth_type) {
                        case 'bearer':
                            $headers['Authorization'] = 'Bearer ' . $config['api_key'];
                            break;
                        case 'header':
                            $header_name = $config['custom_header'] ?? 'X-API-Key';
                            $headers[$header_name] = $config['api_key'];
                            break;
                        case 'basic':
                            $headers['Authorization'] = 'Basic ' . base64_encode($config['api_key']);
                            break;
                    }
                }
                break;
        }

        if ($platform_id === 'openai' && !empty($config['organization_id'])) {
            $headers['OpenAI-Organization'] = $config['organization_id'];
        }

        return $headers;
    }

    
    public static function validate_external_url( $url ) {
        $parsed = wp_parse_url( $url );

        if ( empty( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
            return new \WP_Error( 'invalid_url_scheme', __( 'Only HTTP and HTTPS URLs are allowed.', 'more-mcp' ) );
        }

        if ( empty( $parsed['host'] ) ) {
            return new \WP_Error( 'invalid_url_host', __( 'URL must include a hostname.', 'more-mcp' ) );
        }

        $host = $parsed['host'];

        $blocked = array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' );
        if ( in_array( strtolower( $host ), $blocked, true ) ) {
            return new \WP_Error( 'blocked_url', __( 'Localhost and loopback addresses are not allowed.', 'more-mcp' ) );
        }

        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return new \WP_Error( 'blocked_url', __( 'Private and reserved IP addresses are not allowed.', 'more-mcp' ) );
            }
        }

        return true;
    }

    public static function get_endpoint($platform_id, $config) {
        $platform = self::get_platform($platform_id);

        if (!$platform) {
            return '';
        }

        if (!empty($config['url'])) {
            return rtrim($config['url'], '/');
        }

        return rtrim($platform['endpoint'], '/');
    }

    public static function test_connection($platform_id, $config) {
        $platform = self::get_platform($platform_id);

        if (!$platform) {
            return [
                'success' => false,
                'message' => 'Invalid platform',
            ];
        }

        $endpoint = self::get_endpoint($platform_id, $config);
        $headers = self::get_auth_headers($platform_id, $config);

        if (!empty($platform['extra_headers']) && is_array($platform['extra_headers'])) {
            $headers = array_merge($headers, $platform['extra_headers']);
        }

        if (empty($endpoint)) {
            return [
                'success' => false,
                'message' => 'No endpoint configured',
            ];
        }

        $test_url = $endpoint;
        if (!empty($platform['test_endpoint'])) {
            $test_url .= $platform['test_endpoint'];
        }

        $url_check = self::validate_external_url( $test_url );
        if ( is_wp_error( $url_check ) ) {
            return [
                'success' => false,
                'message' => $url_check->get_error_message(),
            ];
        }

        if ($platform['auth_type'] === 'query' && !empty($config['api_key'])) {
            $test_url = add_query_arg($platform['auth_param'], $config['api_key'], $test_url);
        }

        $request_args = [
            'method' => $platform['test_method'] ?? 'GET',
            'headers' => $headers,
            'timeout' => 10,
        ];

        
        
        if (($platform['test_method'] ?? 'GET') === 'POST' && !empty($platform['test_body'])) {
            $test_body = $platform['test_body'];
            if (!empty($config['model']) && array_key_exists('model', $test_body)) {
                $test_body['model'] = $config['model'];
            }
            $request_args['body'] = wp_json_encode($test_body);
        }

        $response = wp_remote_request($test_url, $request_args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300) {
            return [
                'success' => true,
                'message' => 'Connection successful!',
                'status_code' => $status_code,
            ];
        } elseif ($status_code === 401 || $status_code === 403) {
            return [
                'success' => false,
                'message' => 'Authentication failed. Please check your API key.',
                'status_code' => $status_code,
            ];
        } else {
            $body = wp_remote_retrieve_body($response);
            $error_detail = '';
            if (!empty($body)) {
                $decoded = json_decode($body, true);
                if (isset($decoded['error']['message'])) {
                    $error_detail = ': ' . $decoded['error']['message'];
                }
            }
            return [
                'success' => false,
                'message' => sprintf('Server responded with status %d%s', $status_code, $error_detail),
                'status_code' => $status_code,
            ];
        }
    }
}
