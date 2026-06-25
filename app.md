La presente estructura pertenece a un proyecto anterior, que al ir modificandolo se volvio muy complejo y
se desalineo bastante con el objetivo que debia ser. Por lo que fue borrado manteniendo solo la estructura
de los manues y algunos detalles.

Aplicacion para la gestion de transportes de cargas de argentina.
Objetivos: al aplicacion en si, debe registrar los movimientos de 1 o mas empresas segun se carguen en la db.

Usuarios: Developer por sobre todos, con acceso total completo, Administrador(creado solo por un developer),
despues del administrador es el unico que puede crear usuarios y asignarles roles especificos de acceso a pagina y funciones.

En caso de haber virias empresas operando la app, un administrador no puede ver datos ni empresas creados por otros admins, tampoco los usuarios de cada admin.

Modulos:
1-VIAJES:  Se registra, cliente, camion, acoplado, chofer(asigando al camion), producto, comision a dador de carga(% o monto fijo), valor de comision, comisionista,
ctg, cp, o remito, segun corresponda, pagador de flete, origen, destino, fecha de carga, tarifa por tn, peso estimado.

Una vez iniciado el viaje se le debe poder agregar gastos(pre cargados en una tabla de la db), adelantos de dinero(para cubrir gastos, en caso de sobrar se toma como adelanto de la ganancia del chofer relacionado a dicho flete), al descargar se ingresan las tn netas, o en su defecto el peso de la tara y bruto.
con esto se calcula las tn descargadas * el precio del flete para obtener el valor a facturar al pagador del flete. Luego de descargado se genera un movimiento en la cta.cte. del chofer
con un ingreso que calcula la ganancia del chofer como Haber, dicha ganancia se setea en porcentaje en la tabla choferes, junto con otros datos.
Este primer modulo tambien debe mostrar un resumen del viaje con los datos mas importantes:
Origen, Destino, Cliente, Producto, CTG(u otro segun corresponda), Tarifa del flete, total estimado(que luego de descargado se transforma en total a facturar), un diferencial que me muestre
si hubo faltante o sobrante de tn con respcto al lo estimado al iniciar el viaje.

luego ese viaje pasa a otra instancia, liquidacion, donde es posible modificar los datos de la instancia anterior, cargar nuevos gastos y adelantos para asociar a dicho viaje.
acreditar a la cta.cte. de chofer el sobrante del adelanto del viaje como Debe, tambien en esta instancia se registra la factura correspondiente a dicho flete.
que al levantar el viaje individual, en base a la tarifa ingresada al inicio y las tn descargadas se obtiene el monto a factura, se registra fecha de la factura, numero de factura
y se cierra la instancia de liquidacion.

otra opicion que debe haber es la cargar una factura de flete y asocicar varios viajes a esa factura, siempre que el pagador del flete sea el mismo, de lo contrario no.

instancia de cobro: aca se levanta el movimiento(factura individual o factura con varios viajes), se ingresan descuento si los hubo(Rertenciones, iibb, u otros descuentos).
se registra fecha de pago y medio de cobro(Efectivo, Tranferencia, cheque, en caso de cheque debe registrar todos los datos de un cheque o e-cheq).
este paso da lugar al registro de ingreso de dinero en la cta de la empresa(Cajas de ahorro bancos, billeteras virtuales u otros.)

2-CLIENTES(personas o empresas a los cuales se les transporta la mercaderia, aca tambien se puedeincluir a comisionistas)

3-CHOFERES: (empleados que manejan los transportes)

4-VEHICULOS: Registro de vehiculos.


Debe haber una pagina principal donde se vea un resumen de los viajes realizados, fletes a cobrar, debe haber un lugar donde me diga que dia ingresan los pagos. lo mas completo posible
pero simple a la vez.

Usar: php, js, html, css puro(con estilos por cada pagina o funcion e importados a main.css).