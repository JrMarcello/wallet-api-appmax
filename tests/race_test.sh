#!/bin/bash

# Gera um identificador único para este teste
ID=$(date +%s)
EMAIL="race_${ID}@test.com"

echo "🏁 Iniciando Race Condition Test"
echo "👤 Criando usuário único: $EMAIL..."

# 1. Cria usuário e pega token
# CORREÇÃO 1: Adicionado header Accept: application/json para evitar redirects
# CORREÇÃO 2: Senha aumentada para 'password123' (min 8 chars)
RESPONSE=$(curl -s -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"name\":\"Race\",\"email\":\"$EMAIL\",\"password\":\"password123\"}")

# Extrai o token
TOKEN=$(echo $RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "❌ Erro ao criar usuário. Resposta da API:"
    echo $RESPONSE
    exit 1
fi

echo "🔑 Token capturado."

# 2. Deposita 1000 (R$ 10,00)
echo "💰 Depositando R$ 10,00..."
curl -s -X POST http://localhost:8000/api/wallet/deposit \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Idempotency-Key: setup-$ID" \
  -d '{"amount": 1000}' > /dev/null

# 3. Dispara 5 saques simultâneos
echo "🚀 Disparando 5 saques simultâneos de R$ 3,00..."
echo "   (Total Tentado: R$ 15,00 | Saldo Disponível: R$ 10,00)"

for i in {1..5}
do
   # Jogamos a saída para /dev/null para não poluir a tela
   curl -s -X POST http://localhost:8000/api/wallet/withdraw \
   -H "Authorization: Bearer $TOKEN" \
   -H "Content-Type: application/json" \
   -H "Accept: application/json" \
   -H "Idempotency-Key: race-$ID-$i" \
   -d '{"amount": 300}' > /dev/null & 
done

wait # Espera todos os processos em background terminarem

echo ""
echo "✅ Requests finalizados."
echo "📊 Verificando saldo final (Esperado: 100 centavos)..."

# 4. Consulta saldo
curl -s -X GET http://localhost:8000/api/wallet/balance \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"

echo "" # Quebra de linha final