# SI5 Global para OpenCart 4

Extensão que completa o produto pelo **código de barras (EAN/UPC/JAN)**: nome, marca, peso, foto, **NCM e CEST**, a partir do catálogo [SI5 Global](https://global.si5.com.br), com milhões de produtos do varejo brasileiro.

*English summary below.*

## Como funciona

1. Na edição do produto aparece o bloco **SI5 Global**. Clique em **Buscar no SI5 Global**.
2. A extensão mostra lado a lado o que o produto tem hoje e o que o catálogo sugere.
3. Marque só o que quer trocar e clique em **Aplicar campos marcados**. Nada é gravado sem essa revisão.

O que é gravado em cada campo:

| Campo | Onde fica no OpenCart |
|---|---|
| Nome | Nome do produto, em todos os idiomas |
| Marca | Fabricante; é criado se ainda não existir |
| Peso | Peso do produto, convertido para a classe de peso dele (g, kg, lb…) |
| Foto | Nova imagem principal; a imagem anterior vira imagem adicional |
| NCM e CEST | Atributos "NCM" e "CEST" no grupo "Fiscal" |

O código de barras é lido do EAN, UPC, JAN ou SKU do produto (no OpenCart 4.1, da aba de códigos do produto) ou do modelo, quando ele é um código válido. Aceita EAN-8, EAN-13, UPC-A e GTIN-14.

NCM e CEST são **sugestões do catálogo**. Confirme com seu contador antes de emitir notas.

## Dados enviados

A extensão consulta `https://global.si5.com.br/api/produtos/{código}` enviando **apenas o código de barras e o seu token**. A foto é baixada só dos servidores de imagem do SI5 Global, sem o token e sem o endereço da loja. Nenhum outro dado (clientes, pedidos, preços) sai da loja.

## Instalação

1. Baixe o `si5global.ocmod.zip` em [Releases](../../releases).
2. Em *Extensões › Instalador*, envie o arquivo e clique em instalar.
3. Em *Extensões › Extensões › Módulos*, instale o **SI5 Global** e clique em editar.
4. Crie uma conta e um token em global.si5.com.br (*Minha conta*); o plano gratuito permite 100 consultas por dia. Cole o token, ligue a situação e salve.

Requisitos: OpenCart 4, PHP 8.0+ com cURL e GD (com suporte a WebP).

Testado no OpenCart 4.1.0.4 com PHP 8.1.

## English

OpenCart 4 extension that fills product name, brand, weight, picture and the Brazilian fiscal codes **NCM / CEST** from the barcode (EAN/UPC/JAN), using the SI5 Global catalogue. Every change is reviewed in the product form before it is saved. Only the barcode and your API token are sent to the API.

## Licença

[GPL-3.0-or-later](LICENSE). © 2026 SI5.
