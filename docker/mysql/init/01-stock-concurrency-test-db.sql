-- TASK-024 (CA-01): banco descartável usado pelo teste de concorrência real
-- de estoque (`tests/Feature/StockConcurrencyMySqlTest.php`). Existe apenas
-- para que o usuário da aplicação possa criar/derrubar esse banco isolado —
-- o teste NUNCA escreve em `watch_crm`.
--
-- Scripts em /docker-entrypoint-initdb.d só rodam quando o volume do MySQL
-- está vazio. Em um ambiente já existente, aplique o grant uma vez:
--   docker exec watch-crm-mysql-1 mysql -uroot -p<MYSQL_ROOT_PASSWORD> \
--     -e "GRANT ALL PRIVILEGES ON \`watch_crm_stock_concurrency\`.* TO 'watchcrm'@'%';"
CREATE DATABASE IF NOT EXISTS `watch_crm_stock_concurrency`;
GRANT ALL PRIVILEGES ON `watch_crm_stock_concurrency`.* TO 'watchcrm'@'%';
FLUSH PRIVILEGES;
