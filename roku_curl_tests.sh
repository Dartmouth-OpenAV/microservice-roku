#!/bin/bash

# Roku Microservice test script
# Replace these variables with your actual values
MICROSERVICE_URL="your-microservice-url"
DEVICE_FQDN="your-device-fqdn"

echo "Testing Roku Microservice..."
echo "Microservice URL: $MICROSERVICE_URL"
echo "Device FQDN: $DEVICE_FQDN"
echo "----------------------------------------"

# GET Power
echo "Testing GET Power..."
curl -X GET "http://${MICROSERVICE_URL}/${DEVICE_FQDN}"
sleep 1

# SET Power
echo "Testing SET Power..."
curl -X PUT "http://${MICROSERVICE_URL}/${DEVICE_FQDN}" \
     -H "Content-Type: application/json" \
     -d '{
    "power_state": "on"
}'
sleep 10

# SET Video Input
echo "Testing SET Video Input..."
curl -X PUT "http://${MICROSERVICE_URL}/${DEVICE_FQDN}" \
     -H "Content-Type: application/json" \
     -d '{
    "video_input_num": 1
}'
sleep 1

# TOGGLE Audio Mute
echo "Testing TOGGLE Audio Mute..."
curl -X POST "http://${MICROSERVICE_URL}/${DEVICE_FQDN}" \
     -H "Content-Type: application/json" \
     -d '{
    "audio_mute": "toggle"
}'
sleep 1

# SET Volume
echo "Testing SET Volume..."
curl -X POST "http://${MICROSERVICE_URL}/${DEVICE_FQDN}" \
     -H "Content-Type: application/json" \
     -d '{
    "audio_volume": "down"
}'
sleep 1

echo "----------------------------------------"
echo "All tests completed."
