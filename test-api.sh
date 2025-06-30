#!/bin/bash

# Script para testar a API
# Configure a API_KEY antes de executar

API_BASE_URL="http://128.201.77.95/addon-api-mkauth"
API_KEY="seu_token_secreto_aqui"

echo "=== Testando API MK-Auth ==="
echo ""

# Função para fazer requisições
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    
    echo "🔍 $method $endpoint"
    
    if [ "$method" = "POST" ]; then
        curl -s -X POST \
            -H "Content-Type: application/json" \
            -H "X-API-Key: $API_KEY" \
            -d "$data" \
            "$API_BASE_URL$endpoint" | jq '.'
    else
        curl -s -H "X-API-Key: $API_KEY" \
            "$API_BASE_URL$endpoint" | jq '.'
    fi
    
    echo ""
    echo "----------------------------------------"
    echo ""
}

# Teste 1: Informações da API
make_request "GET" "/api/v1/info"

# Teste 2: Chamados abertos
make_request "GET" "/api/v1/chamados/abertos"

# Teste 3: Buscar cliente (compatibilidade)
make_request "GET" "/buscacliente.php?codigo=123"

# Teste 4: Listar clientes
make_request "GET" "/api/v1/clientes?limit=5"

# Teste 5: Buscar cliente RESTful
make_request "GET" "/api/v1/clientes/123"

# Teste 6: Chamados fechados
make_request "GET" "/api/v1/chamados/fechados?data=2024-12"

# Teste 7: Relatório por grupo
make_request "GET" "/api/v1/relatorios/grupos?data=2024-12"

# Teste 8: Criar cliente (descomente para testar)
# make_request "POST" "/api/v1/clientes" '{"codigo":"TESTE001","nome":"Cliente Teste","grupo":"teste","ativo":true}'

echo "✅ Testes concluídos!"
echo ""
echo "💡 Dicas:"
echo "- Configure a API_KEY no arquivo antes de executar"
echo "- Instale jq para melhor formatação: sudo apt install jq"
echo "- Ajuste o API_BASE_URL conforme sua configuração"
