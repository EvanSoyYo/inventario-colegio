#include <array>
#include <cstring>
#include <fstream>
#include <stdexcept>
#include <string>

// Prototipos: declaran las funciones C++ antes de su uso.
extern "C" int calcular_dias_atraso(int fechaLimiteEpoch, int hoyEpoch);
extern "C" int validar_codigo_equipo(const char* codigo);
extern "C" int demostrar_arreglo_poo();
extern "C" int prueba_archivos_virtuales();

struct EquipoDemo {
    int id = 0;
    std::array<char, 41> codigo{};
    std::string nombre;
    bool disponible = true;

    // Sobrecarga del operador == para comparar equipos por identificador.
    bool operator==(const EquipoDemo& otro) const {
        return id == otro.id;
    }
};

class InventarioDemo {
private:
    std::array<EquipoDemo, 16> equipos_{};
    std::size_t cantidad_ = 0;

public:
    bool agregar(const EquipoDemo& equipo) {
        if (cantidad_ >= equipos_.size() || buscar(equipo.codigo.data()) != nullptr) {
            return false;
        }
        equipos_[cantidad_] = equipo;
        ++cantidad_;
        return true;
    }

    // Devuelve un apuntador al equipo encontrado, o nullptr si no existe.
    const EquipoDemo* buscar(const char* codigo) const {
        if (codigo == nullptr) {
            return nullptr;
        }
        for (std::size_t i = 0; i < cantidad_; ++i) {
            if (std::strcmp(equipos_[i].codigo.data(), codigo) == 0) {
                return &equipos_[i];
            }
        }
        return nullptr;
    }
};

extern "C" int calcular_dias_atraso(int fechaLimiteEpoch, int hoyEpoch) {
    const int dias = hoyEpoch - fechaLimiteEpoch;
    return dias > 0 ? dias : 0;
}

extern "C" int validar_codigo_equipo(const char* codigo) {
    if (codigo == nullptr) {
        return 0;
    }

    const std::string texto(codigo); // Copia segura desde el apuntador recibido.
    if (texto.size() < 2 || texto.size() > 40) {
        return 0;
    }

    for (const unsigned char caracter : texto) {
        const bool letraONumero =
            (caracter >= 'A' && caracter <= 'Z') ||
            (caracter >= 'a' && caracter <= 'z') ||
            (caracter >= '0' && caracter <= '9');
        if (!letraONumero && caracter != '-' && caracter != '_') {
            return 0;
        }
    }
    return 1;
}

extern "C" int demostrar_arreglo_poo() {
    InventarioDemo inventario;
    EquipoDemo equipo;
    equipo.id = 1;
    std::strncpy(equipo.codigo.data(), "WASM-001", equipo.codigo.size() - 1);
    equipo.nombre = "Equipo de demostracion";

    if (!inventario.agregar(equipo)) {
        return 0;
    }
    const EquipoDemo* encontrado = inventario.buscar("WASM-001");
    return encontrado != nullptr && *encontrado == equipo ? 1 : 0;
}

// Usa flujos de C++ sobre el sistema de archivos virtual de WebAssembly.
extern "C" int prueba_archivos_virtuales() {
    try {
        std::ofstream salida("/inventario_demo.csv");
        if (!salida) {
            throw std::runtime_error("No se pudo abrir el archivo de salida.");
        }
        salida << "codigo,nombre\nWASM-001,Equipo de demostracion\n";
        salida.close();

        std::ifstream entrada("/inventario_demo.csv");
        if (!entrada) {
            throw std::runtime_error("No se pudo abrir el archivo de entrada.");
        }
        std::string linea;
        int lineas = 0;
        while (std::getline(entrada, linea)) {
            ++lineas;
        }
        return lineas;
    } catch (const std::exception&) {
        return -1;
    }
}
