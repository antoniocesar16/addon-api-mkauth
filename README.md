# MK-Auth API

API REST moderna em PHP puro com as melhores práticas de desenvolvimento.

## 🚀 Recursos

- **Roteamento RESTful**: Sistema de rotas limpo e organizado
- **Segurança**: Autenticação via API Key, queries preparadas, sanitização
- **Estrutura MVC**: Controllers organizados e separação de responsabilidades
- **Tratamento de Erros**: Respostas padronizadas e tratamento de exceções
- **CORS**: Configurado para APIs cross-origin
- **Paginação**: Listagens com paginação automática
- **Compatibilidade**: Mantém compatibilidade com código existente

## 📋 Estrutura do Projeto

```
addon-api-mkauth/
├── config/
│   └── config.php          # Configurações gerais
├── core/
│   ├── APIRouter.php       # Sistema de roteamento
│   └── Database.php        # Classe de banco de dados
├── controllers/
│   ├── ClienteController.php
│   └── ChamadoController.php
├── index.php               # Ponto de entrada da API
├── .htaccess              # Configurações do Apache
└── README.md              # Este arquivo
```

#### obs: mova para a pasta: /var/www, ou seja, caminho completo deve ficar assim: /var/www/addon-api-mkauth <br>exemplo de requisição: https://<SEU_HOST>/addon-api-mkauth/

## 🔧 Configuração

1. **Configurar API Key**:
   ```php
   // Em config/config.php
   define('API_KEY', 'seu_token_secreto_aqui');
   ```

2. **Verificar conexão com banco**:
   - O arquivo `conectar.php` deve estar configurado corretamente

3. **Configurar Apache**:
   - Certifique-se de que o mod_rewrite está habilitado
   - O arquivo `.htaccess` está configurado

## 📖 Endpoints da API

### Autenticação
Todas as rotas requerem API Key:
- **Header**: `X-API-Key: seu_token_aqui`
- **GET Parameter**: `?api=seu_token_aqui`

### Clientes

#### Listar Clientes
```http
GET /api/v1/clientes?page=1&limit=10&grupo=exemplo&ativo=1
```

#### Buscar Cliente por Código
```http
GET /api/v1/clientes/{codigo}
GET /api/v1/clientes/buscar?codigo=123
```

#### Criar Cliente
```http
POST /api/v1/clientes
Content-Type: application/json

{
  "codigo": "123",
  "nome": "João Silva",
  "grupo": "grupo1",
  "ativo": true
}
```

### Chamados

#### Chamados Abertos
```http
GET /api/v1/chamados/abertos
```

#### Chamados Fechados
```http
GET /api/v1/chamados/fechados?data=2024-12&grupo=suporte
```

#### Chamados Fechados no Dia
```http
GET /api/v1/chamados/fechados/dia?dia=30&mes=12&ano=2024
```

#### Relatório por Grupo
```http
GET /api/v1/relatorios/grupos?data=2024-12
```

### Informações da API
```http
GET /api/v1/info
```

## 🔄 Compatibilidade

A API mantém compatibilidade com os endpoints antigos:
- `/buscacliente.php?codigo=123&api=token`
- `/chamadoaberto.php?api=token`
- `/chamadofechado.php?api=token`
- `/chamadofechadodia.php?api=token`

## 📝 Formato de Resposta

Todas as respostas seguem o padrão:

```json
{
  "success": true,
  "status_code": 200,
  "timestamp": "2024-12-30 10:30:00",
  "data": {
    // Dados da resposta
  }
}
```

### Códigos de Status
- `200`: Sucesso
- `201`: Criado com sucesso
- `400`: Requisição inválida
- `401`: Não autorizado (API Key inválida)
- `404`: Não encontrado
- `409`: Conflito (recurso já exists)
- `500`: Erro interno do servidor

## 🛡️ Segurança

- **Queries Preparadas**: Previne SQL Injection
- **Sanitização**: Todos os inputs são sanitizados
- **API Key**: Autenticação obrigatória
- **Headers de Segurança**: Configurados no .htaccess
- **Validação de Entrada**: Parâmetros obrigatórios validados

## 🔧 Desenvolvimento

### Adicionando Novos Endpoints

1. **Criar Controller** (ou usar existente):
   ```php
   class NovoController {
       public function metodo() {
           // Lógica do endpoint
           $this->api->sendResponse(200, $data);
       }
   }
   ```

2. **Registrar Rota** em `index.php`:
   ```php
   $api->addRoute('GET', 'api/v1/novo-endpoint', [$controller, 'metodo']);
   ```

### Testando a API

```bash
# Teste básico
curl -H "X-API-Key: seu_token_aqui" http://localhost/addon-api-mkauth/api/v1/info

# Buscar cliente
curl -H "X-API-Key: seu_token_aqui" http://localhost/addon-api-mkauth/api/v1/clientes/123

# Criar cliente
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: seu_token_aqui" \
  -d '{"codigo":"456","nome":"Maria Santos"}' \
  http://localhost/addon-api-mkauth/api/v1/clientes
```

## 🏆 Melhores Práticas Implementadas

1. **Separação de Responsabilidades**: MVC pattern
2. **Queries Preparadas**: Segurança contra SQL Injection
3. **Tratamento de Erros**: Try-catch em todos os métodos
4. **Validação de Entrada**: Parâmetros obrigatórios validados
5. **Respostas Padronizadas**: Formato JSON consistente
6. **Documentação**: Código bem documentado
7. **Compatibilidade**: Mantém API antiga funcionando
8. **Performance**: Paginação e queries otimizadas
9. **Segurança**: Headers de segurança e autenticação
10. **Manutenibilidade**: Código organizado e extensível
