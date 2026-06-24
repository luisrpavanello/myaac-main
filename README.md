# MyAAC

MyAAC e o Automatic Account Creator usado como site/painel web do servidor. Ele permite criar contas, personagens, gerenciar recursos web e integrar o site diretamente ao banco de dados do Canary.

Neste workspace, o arquivo `config.local.php` ja esta preparado para ambiente local, apontando para o servidor Canary em `/Users/luispavanello/Dev/ProjectOT/canary/` e para o banco `canary`.

## Estrutura Importante

| Caminho | Descricao |
| --- | --- |
| `index.php` | Entrada principal do site. |
| `install/` | Instalador web do MyAAC. |
| `admin/` | Painel administrativo. |
| `config.php` | Configuracao base do MyAAC. |
| `config.local.php` | Configuracao local/sensivel; sobrescreve valores do `config.php`. |
| `system/` | Core do MyAAC. |
| `plugins/` | Plugins instalaveis. |
| `templates/` | Temas/templates do site. |
| `nginx-sample.conf` | Exemplo de configuracao para Nginx. |
| `.htaccess.dist` | Modelo de regras para Apache. |

## Requisitos

- PHP 7.4 ou superior.
- MySQL/MariaDB.
- Extensao PHP PDO MySQL.
- Extensao PHP XML.
- Extensao PHP ZIP.
- Servidor web Apache, Nginx ou PHP built-in server para desenvolvimento.
- Banco do Canary criado e com `schema.sql` importado.

Extensoes comuns em Linux:

```bash
sudo apt install php php-mysql php-xml php-zip
```

## Configuracao

O arquivo recomendado para ajustes locais e `config.local.php`.

Configuracao local atual:

```php
$config['server_path'] = getenv('MYAAC_SERVER_PATH') ?: '/Users/luispavanello/Dev/ProjectOT/canary/';
$config['site_url'] = getenv('MYAAC_SITE_URL') ?: 'http://localhost:8080/';
$config['database_host'] = getenv('CANARY_DB_HOST') ?: (getenv('MYAAC_DB_HOST') ?: '127.0.0.1');
$config['database_user'] = getenv('CANARY_DB_USER') ?: (getenv('MYAAC_DB_USER') ?: 'canary');
$config['database_password'] = getenv('CANARY_DB_PASSWORD') ?: (getenv('MYAAC_DB_PASSWORD') ?: 'canary');
$config['database_name'] = getenv('CANARY_DB_NAME') ?: (getenv('MYAAC_DB_NAME') ?: 'canary');
```

Voce pode alterar diretamente o arquivo ou usar variaveis de ambiente:

```bash
export MYAAC_SITE_URL="http://localhost:8080/"
export MYAAC_SERVER_PATH="/Users/luispavanello/Dev/ProjectOT/canary/"
export MYAAC_DB_HOST="127.0.0.1"
export MYAAC_DB_USER="canary"
export MYAAC_DB_PASSWORD="canary"
export MYAAC_DB_NAME="canary"
```

## Como Iniciar em Desenvolvimento

Na raiz deste diretorio:

```bash
php -S 127.0.0.1:8080
```

Acesse:

```text
http://127.0.0.1:8080/
```

Se o projeto ainda nao estiver instalado no banco, acesse:

```text
http://127.0.0.1:8080/install
```

Siga o instalador e confirme que as credenciais apontam para o mesmo banco usado pelo Canary.

## Apache

Copie o projeto para o document root do Apache ou aponte um VirtualHost para este diretorio. Em Linux, ajuste permissoes para o usuario do servidor web:

```bash
sudo chown -R www-data:www-data /caminho/para/myaac-main
sudo chmod 755 -R /caminho/para/myaac-main
sudo chmod 777 -R outfits/ system/ images/ plugins/ tools/
```

Se for usar URLs amigaveis, habilite `mod_rewrite` e aplique as regras de `.htaccess.dist`.

## Nginx

Use `nginx-sample.conf` como base para o server block. Garanta que:

- O `root` aponte para este diretorio.
- PHP-FPM esteja configurado.
- Rewrites sejam equivalentes aos do Apache.

## Dependencias de Desenvolvimento Frontend

O projeto possui `package.json` apenas para ferramentas de formatacao/lint:

```bash
npm install
```

Nao e necessario rodar build frontend para iniciar o site em PHP.

## Integracao com Canary

Para funcionar corretamente:

1. O banco `canary` deve existir.
2. O `schema.sql` do Canary deve ter sido importado.
3. `config.local.php` deve apontar para o mesmo banco.
4. `server_path` deve apontar para a pasta do Canary.

## Solucao de Problemas

| Problema | Verificacao |
| --- | --- |
| Tela branca/erro 500 | Confira logs do PHP/servidor web e extensoes PHP instaladas. |
| Erro de banco | Confira host, usuario, senha, nome do banco e permissoes. |
| Instalador nao abre | Confirme que a pasta `install/` existe e que a URL esta correta. |
| Imagens/cache nao gravam | Ajuste permissoes de `outfits/`, `system/`, `images/`, `plugins/` e `tools/`. |
| Site gera links errados | Ajuste `site_url` em `config.local.php` ou `MYAAC_SITE_URL`. |

## Licenca

Distribuido sob a GNU Public License. Consulte `LICENSE` para detalhes.
