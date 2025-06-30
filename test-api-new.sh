#!/bin/bash

# Script para testar a API de Títulos
# Configure sua API_KEY e BASE_URL antes de executar

API_KEY="sua_api_key_aqui"
BASE_URL="http://localhost/addon-api-mkauth/api"

echo "=== TESTANDO API DE TÍTULOS ==="
echo ""

# Teste 1: Listar títulos
echo "1. Testando listagem de títulos..."
curl -X GET "$BASE_URL/titulos?page=1&limit=5" \
     -H "X-API-Key: $API_KEY" \
     -H "Content-Type: application/json" \
     | jq '.'

echo ""
echo "----------------------------------------"
echo ""

# Teste 2: Buscar título específico
echo "2. Testando busca de título específico..."
TITULO_ID="1"  # Altere para um ID válido
curl -X GET "$BASE_URL/titulos/$TITULO_ID" \
     -H "X-API-Key: $API_KEY" \
     -H "Content-Type: application/json" \
     | jq '.'

echo ""
echo "----------------------------------------"
echo ""

# Teste 3: Buscar títulos por cliente
echo "3. Testando busca por cliente..."
CLIENTE="12345"  # Altere para um cliente válido
curl -X GET "$BASE_URL/titulos/cliente/$CLIENTE" \
     -H "X-API-Key: $API_KEY" \
     -H "Content-Type: application/json" \
     | jq '.'

echo ""
echo "----------------------------------------"
echo ""

# Teste 4: Busca múltipla
echo "4. Testando busca múltipla..."
curl -X POST "$BASE_URL/titulos/search" \
     -H "X-API-Key: $API_KEY" \
     -H "Content-Type: application/json" \
     -d '{
       "login": ["cliente1", "cliente2"],
       "cpf_cnpj": ["12345678901"],
       "status": "aberto"
     }' | jq '.'

echo ""
echo "----------------------------------------"
echo ""

# Teste 5: Receber título (descomente e ajuste UUID)
echo "5. Testando recebimento de título..."
# UUID_TITULO="uuid-do-titulo-aqui"
# curl -X PUT "$BASE_URL/titulos/$UUID_TITULO/receber" \
#      -H "X-API-Key: $API_KEY" \
#      -H "Content-Type: application/json" \
#      -d '{
#        "valor": 150.50,
#        "forma": "PIX",
#        "coletor": "TESTE_API"
#      }' | jq '.'

echo "Teste de recebimento comentado - descomente e configure UUID válido"

echo ""
echo "----------------------------------------"
echo ""

# Teste 6: Teste de erro (sem API key)
echo "6. Testando erro sem API Key..."
curl -X GET "$BASE_URL/titulos" \
     -H "Content-Type: application/json" \
     | jq '.'

echo ""
echo "=== TESTES FINALIZADOS ==="
