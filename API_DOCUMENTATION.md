# API REST - Títulos

Esta é uma API REST moderna desenvolvida em PHP puro seguindo as melhores práticas.

## 🚀 Características

- **Arquitetura RESTful**: Endpoints seguem padrões REST
- **Validação robusta**: Validação de parâmetros e sanitização de dados
- **Tratamento de erros**: Respostas padronizadas para todos os tipos de erro
- **Segurança**: Autenticação por API Key e proteção contra SQL Injection
- **Paginação**: Sistema de paginação eficiente
- **Transações**: Operações críticas protegidas por transações de banco
- **CORS**: Configurado para requisições cross-origin

## 📋 Endpoints

### 1. Listar Títulos
```
GET /api/titulos?page=1&limit=20&status=aberto&cliente=12345
```

**Parâmetros de consulta:**
- `page` (opcional): Página (padrão: 1)
- `limit` (opcional): Itens por página (padrão: 20, máximo: 100)
- `status` (opcional): Filtrar por status
- `cliente` (opcional): Filtrar por login ou CPF/CNPJ

**Resposta:**
```json
{
  "success": true,
  "status_code": 200,
  "timestamp": "2025-06-30 10:30:00",
  "data": {
    "titulos": [...],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_records": 100,
      "per_page": 20,
      "has_next": true,
      "has_prev": false
    }
  }
}
```

### 2. Buscar Título Específico
```
GET /api/titulos/{id}
```

**Parâmetros:**
- `id`: ID ou UUID do título

### 3. Buscar Títulos por Cliente
```
GET /api/titulos/cliente/{cliente}?status=aberto
```

**Parâmetros:**
- `cliente`: Login ou CPF/CNPJ do cliente
- `status` (opcional): Filtrar por status

### 4. Busca Múltipla de Títulos
```
POST /api/titulos/search
```

**Body:**
```json
{
  "login": ["cliente1", "cliente2"],
  "cpf_cnpj": ["12345678901", "98765432100"],
  "status": "aberto"
}
```

### 5. Receber Título
```
PUT /api/titulos/{uuid}/receber
```

**Body:**
```json
{
  "valor": 150.50,
  "forma": "PIX",
  "coletor": "OPERADOR01"
}
```

### 6. Estornar Título
```
PUT /api/titulos/{uuid}/estornar
```

**Body:**
```json
{
  "usuario": "OPERADOR01"
}
```

### 7. Editar Título
```
PUT /api/titulos/{uuid}
```

**Body:**
```json
{
  "valor": 200.00,
  "datavenc": "2025-07-15",
  "observacoes": "Título atualizado"
}
```

### 8. Excluir Título
```
DELETE /api/titulos/{uuid}
```

## 🔐 Autenticação

Todas as requisições devem incluir a API Key:

**Via Header (Recomendado):**
```
X-API-Key: sua_api_key_aqui
```

**Via GET (Compatibilidade):**
```
GET /api/titulos?api=sua_api_key_aqui
```

## 📝 Formato das Respostas

Todas as respostas seguem o padrão:

```json
{
  "success": true|false,
  "status_code": 200,
  "timestamp": "2025-06-30 10:30:00",
  "data": {
    // dados da resposta ou erro
  }
}
```

## ⚠️ Códigos de Status HTTP

- `200 OK`: Sucesso
- `400 Bad Request`: Parâmetros inválidos
- `401 Unauthorized`: API Key inválida
- `404 Not Found`: Recurso não encontrado
- `500 Internal Server Error`: Erro interno

## 🛠️ Melhorias Implementadas

### 1. **Estrutura de Classes**
- `APIRouter`: Gerencia roteamento e respostas
- `Database`: Operações de banco de dados com prepared statements
- `TituloController`: Lógica de negócio dos títulos

### 2. **Segurança**
- Prepared statements para prevenir SQL Injection
- Sanitização de dados de entrada
- Validação de parâmetros obrigatórios
- Autenticação por API Key

### 3. **Tratamento de Erros**
- Try-catch em todos os métodos
- Mensagens de erro padronizadas
- Logs de erro (pode ser implementado)

### 4. **Performance**
- Paginação eficiente
- Queries otimizadas
- Uso de índices de banco (recomendado)

### 5. **Manutenibilidade**
- Código organizado em classes
- Métodos pequenos e focados
- Comentários e documentação

## 🚀 Como Usar

1. **Configure sua API Key** em `config/config.php`:
```php
define('API_KEY', 'sua_chave_secreta_aqui');
```

2. **Configure o banco de dados** em `conectar.php`

3. **Acesse via HTTP**:
```bash
curl -X GET "https://seudominio.com/api/titulos" \
     -H "X-API-Key: sua_api_key_aqui"
```

## 📊 Exemplo de Uso com JavaScript

```javascript
const API_BASE = 'https://seudominio.com/api';
const API_KEY = 'sua_api_key_aqui';

// Buscar títulos
async function buscarTitulos(page = 1) {
  const response = await fetch(`${API_BASE}/titulos?page=${page}`, {
    headers: {
      'X-API-Key': API_KEY,
      'Content-Type': 'application/json'
    }
  });
  
  return await response.json();
}

// Receber título
async function receberTitulo(uuid, dados) {
  const response = await fetch(`${API_BASE}/titulos/${uuid}/receber`, {
    method: 'PUT',
    headers: {
      'X-API-Key': API_KEY,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(dados)
  });
  
  return await response.json();
}
```

## 🔧 Requisitos

- PHP 7.4+
- MySQL/MariaDB
- Módulo PDO ou MySQLi
- mod_rewrite (Apache) ou equivalente

## 📈 Próximas Melhorias

- [ ] Sistema de logs
- [ ] Cache de consultas
- [ ] Rate limiting
- [ ] Documentação OpenAPI/Swagger
- [ ] Testes automatizados
- [ ] Monitoramento de performance
