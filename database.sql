CREATE TABLE IF NOT EXISTS urls (
    id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name varchar(255) UNIQUE NOT NULL,
    created_at timestamp NOT NULL
);

CREATE TABLE url_checks (
    id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    url_id bigint REFERENCES urls (id),
    status_code integer,
    h1 varchar(255),
    title varchar(255),
    description text,
    created_at timestamp NOT NULL
);
-- postgresql://mikhaelhan:mha7X0Poc7Gv0N7k06o6tPGmdDRmvMSi@dpg-d7gaevvlk1mc7383qj5g-a.oregon-postgres.render.com/php_project_9_3vzw