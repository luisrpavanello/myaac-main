# Sprites automáticos de Daily Boosts

O card **Daily Boosts** usa o `looktype` persistido pelo Canary em
`boosted_creature` e `boosted_boss`. Não depende de nomes de monstros, de uma
lista manual nem de GIFs enviados ao site.

## Funcionamento

1. O Canary seleciona a criatura e o boss do dia e grava seus `looktype` no
   banco compartilhado.
2. O MyAAC inicia uma rotina de sincronização e a repete a cada cinco minutos.
3. A rotina verifica se existe um GIF para cada `looktype` atual em
   `images/library/daily-boost/`.
4. Quando falta um arquivo, ela usa os assets oficiais do OTClient 1511 para
   gerar um GIF animado transparente.
5. O template do site encontra o GIF pelo `looktype` e o exibe.

Isso cobre automaticamente qualquer criatura ou boss selecionado no futuro.
Os sprites já existentes não são renderizados novamente.

## Operação manual

Para forçar a geração dos dois boosts atuais:

```bash
docker compose -f canary/docker/docker-compose.yml exec myaac \
  php /var/www/html/system/bin/sync_daily_boost_sprites.php
```

Para conferir se o card tem os dois arquivos necessários:

```bash
docker compose -f canary/docker/docker-compose.yml exec myaac \
  php /var/www/html/system/bin/audit_daily_boost_sprites.php
```

Para executar a rotina leve, que cria somente arquivos ausentes:

```bash
docker compose -f canary/docker/docker-compose.yml exec myaac \
  php /var/www/html/system/bin/sync_daily_boost_sprites.php --if-missing
```

## Diagnóstico

O ciclo de sincronização escreve no log do serviço `myaac`. Consulte-o com:

```bash
docker compose -f canary/docker/docker-compose.yml logs --tail=100 myaac
```

Se um export falhar, confirme que `/otclient/data/things/1511` está montado e
que contém `catalog-content.json`. O Docker quickstart fornece esse mount e
inclui Python 3 no container MyAAC para executar o exportador.
