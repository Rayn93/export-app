import csv
import random

# Listy do losowego generowania danych
colors = ["Czerwony", "Niebieski", "Biały", "Czarny", "Zielony", "Żółty", "Szary"]
categories = ["Koszulka", "Buty", "Kubek", "Torba", "Lampa", "Kurtka", "Zegarek"]
vendors = ["EcoWear", "SportMax", "HomeVibe", "TrendyShop", "TechLite", "FashionPeak"]
product_types = ["Clothing", "Footwear", "Homeware", "Accessories", "Lighting", "Outerwear", "Watches"]
tags = ["nowość", "bestseller", "promo", "eko", "premium"]

# Otwórz plik CSV do zapisu
with open('shopify_1000_products.csv', 'w', newline='', encoding='utf-8') as file:
    writer = csv.writer(file)

    # Nagłówki zgodne z formatem Shopify
    headers = [
        "Handle", "Title", "Body (HTML)", "Vendor", "Product Type", "Tags",
        "Published", "Variant Price", "Variant SKU", "Variant Inventory Qty", "Image Src"
    ]
    writer.writerow(headers)

    # Generowanie 1000 produktów
    for i in range(1, 1001):
        # Unikalny Handle
        handle = f"product-{i}"

        # Losowy tytuł produktu
        category = random.choice(categories)
        color = random.choice(colors)
        title = f"{category} {color} {i}"

        # Opis w formacie HTML
        body_html = f"<p>Wysokiej jakości {category.lower()} w kolorze {color.lower()}. Idealny do codziennego użytku.</p>"

        # Losowy dostawca i typ produktu
        vendor = random.choice(vendors)
        product_type = random.choice(product_types)

        # Losowe tagi (2–3 tagi na produkt)
        product_tags = ", ".join(random.sample(tags, random.randint(2, 3)))

        # Ustawienie publikacji
        published = "true"

        # Losowa cena (10–200 PLN z dwoma miejscami po przecinku)
        price = round(random.uniform(10, 200), 2)

        # Unikalny SKU
        sku = f"SKU-{i:04d}"  # np. SKU-0001, SKU-0002

        # Losowa ilość w magazynie (5–50)
        inventory_qty = random.randint(5, 50)

        # Opcjonalny link do zdjęcia (puste lub przykładowe z Burst)
        image_src = ""  # Możesz dodać np. "https://burst.shopify.com/photos/sample.jpg"

        # Zapisanie wiersza do CSV
        row = [
            handle, title, body_html, vendor, product_type, product_tags,
            published, price, sku, inventory_qty, image_src
        ]
        writer.writerow(row)

print("Plik 'shopify_1000_products.csv' został wygenerowany z 1000 produktami!")