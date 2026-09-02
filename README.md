# Threeebs :3

> Plataforma em construção para quem cria na web.

Este é o repositório público oficial do **Threeebs :3**, nome técnico **3eb.site**.

## Estado atual

O projeto está em fase **PoC / Alpha**. Esta versão é experimental, pode conter falhas e ainda pode receber mudanças incompatíveis.

## Requisitos

- Docker Engine;
- Docker Compose v2;
- Bash.

## Instalação local

```bash
git clone https://github.com/Tiao-gpt/threeebs.git
cd threeebs
bash scripts/install.sh
```

Na primeira execução, o instalador cria o arquivo `.env` e interrompe o processo. Edite esse arquivo, substitua todos os valores iniciados por `TROQUE_` e execute novamente:

```bash
bash scripts/install.sh
```

A configuração de exemplo usa somente `127.0.0.1` e URLs locais. Depois da inicialização:

- Portal: `http://localhost:6011`
- Admin: `http://localhost:6015`
- Sandbox: `http://localhost:6016`
- Host/Preview: `http://localhost:6010`

Para verificar os serviços:

```bash
docker compose ps
```

Para encerrar:

```bash
docker compose down
```

O phpMyAdmin é opcional e pode ser iniciado com:

```bash
docker compose --profile tools up -d phpmyadmin
```

## Segurança

Nunca versione o arquivo `.env`, credenciais do Cloudflare, backups ou dados de produção. Consulte [SECURITY.md](SECURITY.md) para relatar vulnerabilidades.

## Contribuição

Leia [CONTRIBUTING.md](CONTRIBUTING.md) antes de abrir uma Issue ou Pull Request. Mudanças públicas relevantes são registradas em [CHANGELOG.md](CHANGELOG.md).

## Licença

Este repositório está sendo publicado **sem uma licença de código aberto**. O fato de o conteúdo estar publicamente visível não concede automaticamente permissão para copiar, modificar ou redistribuir o projeto.
