# 🖼️ WebP Gallery Converter

Plugin de WordPress que **converte automaticamente as imagens JPG/PNG da galeria
para o formato WebP**, substituindo as imagens pesadas por versões mais leves e
deixando o seu site muito mais rápido.

---

## ✨ O que o plugin faz

- **Converte no upload:** ao enviar uma imagem, gera o `.webp` da imagem original
  e de todas as miniaturas (thumbnails) automaticamente.
- **Converte em massa:** uma tela no admin converte todas as imagens que já
  estavam na Biblioteca de Mídia (com barra de progresso).
- **Entrega inteligente:** no front-end, troca `.jpg`/`.png` por `.webp` apenas
  para navegadores que suportam o formato.
- **Configurável:** qualidade, conversão no upload, substituição no front e
  manter (ou não) os arquivos originais.
- **Compatível com GD e Imagick.**

---

## 📁 Estrutura do projeto

```
webp-gallery-converter/
├── webp-gallery-converter.php   # Arquivo principal: cabeçalho, constantes e inicialização
├── uninstall.php                # Limpeza do banco ao remover o plugin
├── readme.txt                   # Readme padrão do diretório de plugins do WordPress
├── README.md                    # Este arquivo (documentação para devs)
├── includes/
│   ├── class-wgc-converter.php  # "Motor": converte imagens e troca URLs no front
│   ├── class-wgc-admin.php      # Tela de admin, menu e configurações
│   └── class-wgc-bulk.php       # Conversão em massa via AJAX (em lotes)
└── assets/
    ├── admin.css                # Estilos da tela de administração
    └── admin.js                 # Lógica AJAX da conversão em massa
```

---

## 🚀 Como rodar e testar no VS Code

Você precisa de um ambiente WordPress local. Abaixo, **3 opções** — escolha a
mais fácil para você.

### Opção A — Local WP (mais simples, recomendado para iniciantes)

1. Baixe e instale o [Local WP](https://localwp.com/) (gratuito).
2. Crie um site novo (ex.: `meu-site.local`).
3. Abra a pasta `app/public/wp-content/plugins/` do site no VS Code.
4. Copie a pasta `webp-gallery-converter` para dentro de `plugins/`.
5. No WordPress (`/wp-admin`), ative o plugin em **Plugins**.
6. Vá em **Mídia → WebP Converter** e teste.

### Opção B — Docker com `@wordpress/env` (recomendado para devs)

Requer [Node.js](https://nodejs.org/) e [Docker](https://www.docker.com/) instalados.

```bash
# 1. Entre na pasta do plugin
cd webp-gallery-converter

# 2. Crie um arquivo .wp-env.json (já incluso neste projeto)

# 3. Suba o ambiente (instala WordPress + monta o plugin automaticamente)
npx @wordpress/env start

# 4. Acesse no navegador:
#    http://localhost:8888   (site)
#    http://localhost:8888/wp-admin   (admin: usuário "admin" / senha "password")

# Para parar o ambiente:
npx @wordpress/env stop
```

O arquivo `.wp-env.json` já mapeia esta pasta como plugin, então ele aparece
ativável no painel.

### Opção C — XAMPP / WAMP / MAMP

1. Instale o [XAMPP](https://www.apachefriends.org/) e suba Apache + MySQL.
2. Baixe o WordPress em `htdocs/` e faça a instalação pelo navegador.
3. Copie a pasta `webp-gallery-converter` para
   `htdocs/wordpress/wp-content/plugins/`.
4. Ative o plugin e teste em **Mídia → WebP Converter**.

> ⚠️ **Importante:** verifique se o PHP tem a extensão **GD com WebP** ou
> **Imagick** habilitada. A própria tela do plugin avisa se o servidor é
> compatível.

---

## 🧪 Roteiro de teste rápido

1. Ative o plugin → abra **Mídia → WebP Converter**.
2. Confira o aviso verde de "Servidor compatível".
3. Envie uma imagem `.jpg` em **Mídia → Adicionar nova**.
4. Verifique que um arquivo `.webp` foi criado ao lado, na pasta
   `wp-content/uploads/AAAA/MM/`.
5. Clique em **Iniciar conversão em massa** e veja a barra de progresso.
6. Abra uma página com imagens e inspecione o HTML: as imagens devem apontar
   para `.webp` (em navegadores compatíveis).

---

## 🔧 Verificando suporte a WebP no PHP

```bash
# GD com suporte a WebP?
php -r "var_dump(function_exists('imagewebp'));"

# Imagick com suporte a WebP?
php -r "var_dump(extension_loaded('imagick') && \Imagick::queryFormats('WEBP'));"
```

---

## 📜 Licença

GPL-2.0-or-later. Veja o cabeçalho do arquivo principal.
