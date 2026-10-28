<?php
/**
 * SI5 Global para OpenCart 4: completa o produto pelo código de barras (nome, marca, peso, foto, NCM e CEST),
 * sempre com revisão antes de gravar. Só o código de barras e o token vão para a API.
 *
 * @author    SI5
 * @copyright 2026 SI5
 * @license   GPL-3.0-or-later
 */
namespace Opencart\Admin\Controller\Extension\Si5global\Module;

require_once DIR_EXTENSION . 'si5global/system/library/si5global_client.php';

class Si5global extends \Opencart\System\Engine\Controller
{
    const VERSAO = '1.0.0';
    const CAMPOS = ['nome', 'marca', 'peso', 'imagem', 'ncm', 'cest'];

    private function sep(): string
    {
        return version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
    }

    /* ---------------- configuração ---------------- */

    public function index(): void
    {
        $this->load->language('extension/si5global/module/si_5global');
        $this->document->setTitle($this->language->get('heading_title'));
        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
            ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')],
            ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/si5global/module/si_5global', 'user_token=' . $this->session->data['user_token'])],
        ];
        $data['save'] = $this->url->link('extension/si5global/module/si_5global' . $this->sep() . 'save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
        $data['tem_token'] = (string) $this->config->get('module_si_5global_token') !== '';
        $data['module_si_5global_status'] = (int) $this->config->get('module_si_5global_status');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/si5global/module/si_5global', $data));
    }

    public function save(): void
    {
        $this->load->language('extension/si5global/module/si_5global');
        $json = [];
        if (!$this->user->hasPermission('modify', 'extension/si5global/module/si_5global')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }
        if (!$json) {
            $this->load->model('setting/setting');
            $token = trim((string) ($this->request->post['module_si_5global_token'] ?? ''));
            if (!empty($this->request->post['module_si_5global_remover'])) {
                $token = '';
            } elseif ($token === '') {
                $token = (string) $this->config->get('module_si_5global_token'); // em branco mantém
            }
            $this->model_setting_setting->editSetting('module_si_5global', [
                'module_si_5global_status' => (int) ($this->request->post['module_si_5global_status'] ?? 0),
                'module_si_5global_token' => $token,
            ]);
            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('si5global');
        $evento = [
            'code' => 'si5global',
            'description' => 'SI5 Global: bloco de consulta na tela do produto',
            'trigger' => 'admin/view/catalog/product_form/after',
            'action' => 'extension/si5global/module/si_5global' . $this->sep() . 'injetar',
            'status' => 1,
            'sort_order' => 0,
        ];
        // OpenCart 4.0.0/4.0.1 recebe parâmetros separados; 4.0.2+ recebe o array
        if (version_compare(VERSION, '4.0.2.0', '>=')) {
            $this->model_setting_event->addEvent($evento);
        } else {
            $this->model_setting_event->addEvent($evento['code'], $evento['description'], $evento['trigger'], $evento['action'], $evento['status'], $evento['sort_order']);
        }
    }

    public function uninstall(): void
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('si5global');
    }

    /* ---------------- tela do produto ---------------- */

    /** Evento admin/view/catalog/product_form/after: acrescenta o bloco SI5 Global antes do fim do formulário. */
    public function injetar(string &$route, array &$args, string &$output): void
    {
        if (!$this->config->get('module_si_5global_status') || !$this->user->hasPermission('modify', 'catalog/product')) {
            return;
        }
        $idProduto = (int) ($this->request->get['product_id'] ?? 0);
        $token = $this->session->data['user_token'];
        $dados = [
            'url_consultar' => html_entity_decode($this->url->link('extension/si5global/module/si_5global' . $this->sep() . 'consultar', 'user_token=' . $token . '&product_id=' . $idProduto)),
            'url_aplicar' => html_entity_decode($this->url->link('extension/si5global/module/si_5global' . $this->sep() . 'aplicar', 'user_token=' . $token . '&product_id=' . $idProduto)),
            'tem_token' => (string) $this->config->get('module_si_5global_token') !== '',
            'id_produto' => $idProduto,
        ];
        $h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        if (!$idProduto) {
            $bloco = '<div class="alert alert-info">SI5 Global: salve o produto para consultar pelo código de barras.</div>';
        } elseif (!$dados['tem_token']) {
            $bloco = '<div class="alert alert-warning">SI5 Global: configure o token em Extensões › Módulos › SI5 Global.</div>';
        } else {
            $bloco = '<div class="card mb-3 si5g" id="si5g" data-consultar="' . $h($dados['url_consultar']) . '" data-aplicar="' . $h($dados['url_aplicar']) . '">'
                . '<div class="card-header"><i class="fa-solid fa-barcode"></i> SI5 Global</div><div class="card-body">'
                . '<button type="button" class="btn btn-primary" id="si5g-consultar">Buscar no SI5 Global</button>'
                . '<div class="form-text">Nada é gravado sem a sua revisão. Só o código de barras e o token vão para o SI5 Global.</div>'
                . '<div id="si5g-revisao" hidden><table class="table table-sm mt-3"><thead><tr><th></th><th>Atual</th><th>Sugerido</th><th class="text-center">Substituir</th></tr></thead><tbody id="si5g-linhas"></tbody></table>'
                . '<p class="text-muted small" id="si5g-ref"></p><button type="button" class="btn btn-success" id="si5g-aplicar">Aplicar campos marcados</button> '
                . '<button type="button" class="btn btn-light" id="si5g-cancelar">Cancelar</button></div>'
                . '<div id="si5g-msg" class="mt-3" role="status" aria-live="polite"></div></div></div>'
                . '<script src="../extension/si5global/admin/view/javascript/si5global.js?v=' . self::VERSAO . '" defer></script>';
        }
        // antes do fim do <form> principal; se o layout mudar, vai no fim da página
        $pos = strripos($output, '</form>');
        $output = $pos !== false ? substr_replace($output, $bloco, $pos, 0) : $output . $bloco;
    }

    private function json(array $dados): void
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->setOutput(json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function cliente(): \Si5GlobalClient
    {
        return new \Si5GlobalClient((string) $this->config->get('module_si_5global_token'), 'SI5Global-OpenCart/' . self::VERSAO);
    }

    private function produto(): array
    {
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new \Exception('Requisição inválida.');
        }
        if (!$this->user->hasPermission('modify', 'catalog/product')) {
            throw new \Exception('Sem permissão para editar produtos.');
        }
        $id = (int) ($this->request->get['product_id'] ?? 0);
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . $id . "'");
        if (!$id || !$q->num_rows) {
            throw new \Exception('Produto não encontrado.');
        }

        return $q->row;
    }

    private function gtinDoProduto(array $p): ?string
    {
        // OpenCart 4.0 guarda os códigos em colunas do produto; o 4.1 passou para a tabela product_code
        $codigos = [];
        if (array_key_exists('ean', $p)) {
            foreach (['ean', 'upc', 'jan', 'sku'] as $campo) {
                $codigos[] = $p[$campo] ?? '';
            }
        } else {
            $q = $this->db->query("SELECT `code`, `value` FROM `" . DB_PREFIX . "product_code` WHERE `product_id` = '" . (int) $p['product_id'] . "' AND `code` IN ('EAN', 'UPC', 'JAN', 'SKU') ORDER BY FIELD(`code`, 'EAN', 'UPC', 'JAN', 'SKU')");
            foreach ($q->rows as $r) {
                $codigos[] = $r['value'];
            }
        }
        $codigos[] = $p['model'] ?? '';
        foreach ($codigos as $c) {
            $g = \Si5GlobalClient::gtin((string) $c);
            if ($g !== null) {
                return $g;
            }
        }

        return null;
    }

    private function idLang(): int
    {
        return (int) $this->config->get('config_language_id');
    }

    /** Converte gramas para a classe de peso do produto. */
    private function pesoNaClasse(float $gramas, int $classe): array
    {
        $classes = $this->db->query("SELECT wc.`weight_class_id`, wc.`value`, wcd.`unit` FROM `" . DB_PREFIX . "weight_class` wc LEFT JOIN `" . DB_PREFIX . "weight_class_description` wcd ON (wc.`weight_class_id` = wcd.`weight_class_id` AND wcd.`language_id` = '" . $this->idLang() . "')")->rows;
        $porUnidade = [];
        $destino = null;
        foreach ($classes as $c) {
            $porUnidade[strtolower((string) $c['unit'])] = (float) $c['value'];
            if ((int) $c['weight_class_id'] === $classe) {
                $destino = $c;
            }
        }
        if (!$destino || (float) $destino['value'] <= 0) {
            throw new \Exception('Classe de peso do produto não encontrada.');
        }
        if (!empty($porUnidade['g'])) {
            $valor = $gramas / $porUnidade['g'] * (float) $destino['value'];
        } elseif (!empty($porUnidade['kg'])) {
            $valor = $gramas / 1000 / $porUnidade['kg'] * (float) $destino['value'];
        } else {
            throw new \Exception('A loja não tem classe de peso em g ou kg.');
        }

        return [round($valor, 3), (string) $destino['unit']];
    }

    private function atributoFiscal(int $idProduto, string $rotulo): string
    {
        $q = $this->db->query("SELECT pa.`text` FROM `" . DB_PREFIX . "product_attribute` pa JOIN `" . DB_PREFIX . "attribute_description` ad ON (ad.`attribute_id` = pa.`attribute_id` AND ad.`language_id` = pa.`language_id`) WHERE pa.`product_id` = '" . $idProduto . "' AND pa.`language_id` = '" . $this->idLang() . "' AND ad.`name` = '" . $this->db->escape($rotulo) . "' LIMIT 1");

        return $q->num_rows ? (string) $q->row['text'] : '';
    }

    public function consultar(): void
    {
        try {
            $p = $this->produto();
            $gtin = $this->gtinDoProduto($p);
            if ($gtin === null) {
                throw new \Exception('Informe um EAN, UPC ou JAN válido no produto (aba Dados) e salve.');
            }
            $s = $this->cliente()->consultar($gtin);
            $id = (int) $p['product_id'];
            $nome = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "product_description` WHERE `product_id` = '" . $id . "' AND `language_id` = '" . $this->idLang() . "'");
            $marca = $p['manufacturer_id'] ? $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "manufacturer` WHERE `manufacturer_id` = '" . (int) $p['manufacturer_id'] . "'") : null;
            $s['peso'] = '';
            if ($s['peso_gramas']) {
                [$v, $un] = $this->pesoNaClasse($s['peso_gramas'], (int) $p['weight_class_id']);
                $s['peso'] = rtrim(rtrim(number_format($v, 3, ',', ''), '0'), ',') . ' ' . $un;
            }
            $atual = [
                'nome' => $nome->num_rows ? html_entity_decode((string) $nome->row['name'], ENT_QUOTES, 'UTF-8') : '',
                'marca' => $marca && $marca->num_rows ? html_entity_decode((string) $marca->row['name'], ENT_QUOTES, 'UTF-8') : '',
                'peso' => (float) $p['weight'] > 0 ? rtrim(rtrim(number_format((float) $p['weight'], 3, ',', ''), '0'), ',') : '',
                'imagem' => $p['image'] ? 'sim' : '',
                'ncm' => $this->atributoFiscal($id, 'NCM'),
                'cest' => $this->atributoFiscal($id, 'CEST'),
            ];
            $this->json(['ok' => true, 'atual' => $atual, 'sugerido' => $s]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'erro' => $e->getMessage()]);
        }
    }

    public function aplicar(): void
    {
        try {
            $p = $this->produto();
            $id = (int) $p['product_id'];
            // o Request do OpenCart aplica htmlspecialchars em todo POST: desfaz antes de ler o JSON
            $d = json_decode(html_entity_decode((string) ($this->request->post['sugerido'] ?? ''), ENT_QUOTES, 'UTF-8'), true);
            $campos = array_values(array_intersect(self::CAMPOS, (array) ($this->request->post['campos'] ?? [])));
            if (!is_array($d) || !$campos) {
                throw new \Exception('Marque pelo menos um campo para aplicar.');
            }
            $alterados = [];
            $langs = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "language`")->rows;

            if (in_array('nome', $campos, true)) {
                $nome = trim(mb_substr(strip_tags((string) ($d['nome'] ?? '')), 0, 255));
                if ($nome === '') {
                    throw new \Exception('Nome sugerido inválido.');
                }
                foreach ($langs as $l) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET `name` = '" . $this->db->escape(htmlspecialchars($nome, ENT_QUOTES, 'UTF-8')) . "' WHERE `product_id` = '" . $id . "' AND `language_id` = '" . (int) $l['language_id'] . "'");
                }
                $alterados[] = 'nome';
            }

            if (in_array('marca', $campos, true) && trim((string) ($d['marca'] ?? '')) !== '') {
                // gravada escapada, como o formulário de fabricantes do OpenCart grava
                $marca = htmlspecialchars(trim(mb_substr(strip_tags((string) $d['marca']), 0, 64)), ENT_QUOTES, 'UTF-8');
                $q = $this->db->query("SELECT `manufacturer_id` FROM `" . DB_PREFIX . "manufacturer` WHERE `name` = '" . $this->db->escape($marca) . "' LIMIT 1");
                if ($q->num_rows) {
                    $idMarca = (int) $q->row['manufacturer_id'];
                } else {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET `name` = '" . $this->db->escape($marca) . "', `image` = '', `sort_order` = '0'");
                    $idMarca = (int) $this->db->getLastId();
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer_to_store` SET `manufacturer_id` = '" . $idMarca . "', `store_id` = '0'");
                }
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `manufacturer_id` = '" . $idMarca . "' WHERE `product_id` = '" . $id . "'");
                $alterados[] = 'marca';
            }

            if (in_array('peso', $campos, true) && !empty($d['peso_gramas']) && (float) $d['peso_gramas'] > 0) {
                [$valor] = $this->pesoNaClasse((float) $d['peso_gramas'], (int) $p['weight_class_id']);
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `weight` = '" . (float) $valor . "' WHERE `product_id` = '" . $id . "'");
                $alterados[] = 'peso';
            }

            if (in_array('imagem', $campos, true) && !empty($d['imagem_url'])) {
                [$bytes] = $this->cliente()->baixarImagem((string) $d['imagem_url']);
                $img = @imagecreatefromstring($bytes);
                if (!$img) {
                    throw new \Exception('O PHP desta loja não consegue ler a foto (GD sem suporte a WebP?).');
                }
                $gtin = preg_replace('/\D/', '', (string) ($d['gtin'] ?? '')) ?: (string) $id;
                $relativo = 'catalog/si5global/' . $gtin . '.jpg';
                if (!is_dir(DIR_IMAGE . 'catalog/si5global')) {
                    mkdir(DIR_IMAGE . 'catalog/si5global', 0755, true);
                }
                imagejpeg($img, DIR_IMAGE . $relativo, 92);
                imagedestroy($img);
                // a foto principal antiga vira foto adicional (nada é perdido)
                if (!empty($p['image']) && $p['image'] !== $relativo) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET `product_id` = '" . $id . "', `image` = '" . $this->db->escape($p['image']) . "', `sort_order` = '0'");
                }
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `image` = '" . $this->db->escape($relativo) . "' WHERE `product_id` = '" . $id . "'");
                $alterados[] = 'foto';
            }

            foreach (['ncm' => ['NCM', 8], 'cest' => ['CEST', 7]] as $campo => [$rotulo, $tam]) {
                $valor = preg_replace('/\D/', '', (string) ($d[$campo] ?? ''));
                if (in_array($campo, $campos, true) && strlen($valor) === $tam) {
                    $idAtrib = $this->atributo($rotulo, $langs);
                    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_attribute` WHERE `product_id` = '" . $id . "' AND `attribute_id` = '" . $idAtrib . "'");
                    foreach ($langs as $l) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_attribute` SET `product_id` = '" . $id . "', `attribute_id` = '" . $idAtrib . "', `language_id` = '" . (int) $l['language_id'] . "', `text` = '" . $this->db->escape($valor) . "'");
                    }
                    $alterados[] = $rotulo;
                }
            }

            if ($alterados) {
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `date_modified` = NOW() WHERE `product_id` = '" . $id . "'");
                $this->cache->delete('product');
            }
            $this->json(['ok' => true, 'alterados' => $alterados]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'erro' => $e->getMessage()]);
        }
    }

    /** Atributo NCM/CEST no grupo "Fiscal" (cria se não existir). */
    private function atributo(string $rotulo, array $langs): int
    {
        $q = $this->db->query("SELECT a.`attribute_id` FROM `" . DB_PREFIX . "attribute` a JOIN `" . DB_PREFIX . "attribute_description` ad ON ad.`attribute_id` = a.`attribute_id` WHERE ad.`name` = '" . $this->db->escape($rotulo) . "' LIMIT 1");
        if ($q->num_rows) {
            return (int) $q->row['attribute_id'];
        }
        $g = $this->db->query("SELECT `attribute_group_id` FROM `" . DB_PREFIX . "attribute_group_description` WHERE `name` = 'Fiscal' LIMIT 1");
        if ($g->num_rows) {
            $idGrupo = (int) $g->row['attribute_group_id'];
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_group` SET `sort_order` = '90'");
            $idGrupo = (int) $this->db->getLastId();
            foreach ($langs as $l) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_group_description` SET `attribute_group_id` = '" . $idGrupo . "', `language_id` = '" . (int) $l['language_id'] . "', `name` = 'Fiscal'");
            }
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute` SET `attribute_group_id` = '" . $idGrupo . "', `sort_order` = '" . ($rotulo === 'NCM' ? 1 : 2) . "'");
        $idAtrib = (int) $this->db->getLastId();
        foreach ($langs as $l) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_description` SET `attribute_id` = '" . $idAtrib . "', `language_id` = '" . (int) $l['language_id'] . "', `name` = '" . $this->db->escape($rotulo) . "'");
        }

        return $idAtrib;
    }
}
